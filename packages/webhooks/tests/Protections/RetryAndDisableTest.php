<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Foundation\Audit\Enums\AuditOutcome;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\DisabledReason;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Mail\EndpointDisabledMail;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Tests\Fixtures\User;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// NOVAS TENTATIVAS com backoff exponencial e DESATIVAÇÃO por falhas seguidas
// — com o relógio CONGELADO (cada passo avança o tempo à mão) e o receptor de
// teste respondendo 500.
// =============================================================================

function failingEndpoint(mixed $test, User $owner): WebhookEndpoint
{
    $receiver = $test->receiver();
    $test->dns->records['falha.invalid'] = ['127.0.0.1'];

    ['endpoint' => $endpoint, 'secret' => $secret] = $test->createEndpoint($owner, ['url' => $receiver->url('/fail', 'falha.invalid')]);
    $receiver->expectSecrets([$secret]);

    return $endpoint;
}

function onlyDelivery(): WebhookDelivery
{
    return Accounts::asSystem('teste', fn () => WebhookDelivery::query()->with('attemptLog')->sole());
}

it('agenda as novas tentativas em 1 min, 5 min, 30 min, 2 h e 12 h e desiste depois da sexta', function (): void {
    $this->freezeSecond();
    config(['webhooks.delivery.disable_after_failures' => 100]);

    $owner = $this->owner();
    failingEndpoint($this, $owner);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $esperas = [];

    foreach ([60, 300, 1800, 7200, 43200] as $espera) {
        $delivery = onlyDelivery();

        expect($delivery->status)->toBe(DeliveryStatus::Retrying);
        $esperas[] = (int) Carbon::now()->diffInSeconds($delivery->next_attempt_at);

        // Antes da hora, o outbox não põe na fila (nem o job reservaria).
        $this->travel($espera - 1)->seconds();
        $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
        expect(onlyDelivery()->attempts)->toBe(count($esperas));

        $this->travel(1)->seconds();
        // O job atrasado se perdeu (fila em memória da suíte é síncrona):
        // quem põe de novo é o outbox.
        $this->travel(301)->seconds();
        $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
    }

    $delivery = onlyDelivery();

    expect($esperas)->toBe([60, 300, 1800, 7200, 43200])
        ->and($delivery->attempts)->toBe(6)
        ->and($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->attemptLog->pluck('attempt')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($delivery->attemptLog->every(fn ($attempt): bool => $attempt->outcome === AttemptOutcome::Failed && $attempt->response_status === 500))->toBeTrue()
        ->and(count($this->receiver->requests()))->toBe(6);

    // Esgotada, não sai mais sozinha.
    $this->travel(1)->days();
    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
    expect(count($this->receiver->requests()))->toBe(6);
});

it('DESATIVA o endpoint depois de N falhas seguidas, encerra o que estava em aberto, avisa dono e admins e registra', function (): void {
    $this->freezeSecond();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $logged->append($message));
    config(['webhooks.delivery.disable_after_failures' => 3]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    $endpoint = failingEndpoint($this, $owner);

    $admin = $this->owner();
    $member = $this->owner();
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $admin->id, 'role' => AccountRole::Admin->value]);
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => AccountRole::Member->value]);

    Mail::fake();

    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);
    Webhooks::dispatch($account, 'order.created', ['n' => 3]);
    Webhooks::dispatch($account, 'order.created', ['n' => 4]);

    $endpoint->refresh();
    $deliveries = Accounts::asSystem('teste', fn () => WebhookDelivery::query()->orderBy('id')->get());

    expect($endpoint->status)->toBe(EndpointStatus::Disabled)
        ->and($endpoint->disabled_reason)->toBe(DisabledReason::Failures)
        ->and($endpoint->consecutive_failures)->toBe(3)
        // As três primeiras falharam (e as em aberto foram encerradas); a
        // quarta nem saiu: o endpoint já não estava ativo.
        ->and($deliveries->pluck('status')->all())->toBe([DeliveryStatus::Failed, DeliveryStatus::Failed, DeliveryStatus::Failed])
        ->and(count($this->receiver->requests()))->toBe(3);

    $row = AuditEvent::query()->where('action', 'webhook_endpoint.disabled')->sole();

    expect($row->outcome)->toBe(AuditOutcome::Success)
        ->and($row->subject_uuid)->toBe($endpoint->uuid)
        ->and($row->tenant_uuid)->toBe($account->uuid)
        ->and($row->changes['reason']['after'])->toBe('failures');

    Mail::assertQueued(EndpointDisabledMail::class, 2);
    Mail::assertQueued(EndpointDisabledMail::class, fn (EndpointDisabledMail $mail): bool => $mail->hasTo($owner->email) && $mail->endpointHost === 'falha.invalid');
    Mail::assertQueued(EndpointDisabledMail::class, fn (EndpointDisabledMail $mail): bool => $mail->hasTo($admin->email));
    Mail::assertNotQueued(EndpointDisabledMail::class, fn (EndpointDisabledMail $mail): bool => $mail->hasTo($member->email));

    expect(collect($logged->getArrayCopy())->filter(fn (MessageLogged $m): bool => $m->message === 'webhooks.endpoint_disabled' && $m->level === 'warning'))->toHaveCount(1);
});

it('um sucesso zera as falhas seguidas', function (): void {
    config(['webhooks.delivery.disable_after_failures' => 3]);

    $owner = $this->owner();
    $endpoint = failingEndpoint($this, $owner);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 2]);
    expect($endpoint->refresh()->consecutive_failures)->toBe(2);

    $endpoint->forceFill(['url' => $this->receiver->url('/ok', 'falha.invalid')])->save();
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 3]);

    expect($endpoint->refresh()->consecutive_failures)->toBe(0)
        ->and($endpoint->status)->toBe(EndpointStatus::Active)
        ->and($endpoint->last_success_at)->not->toBeNull();
});

it('o e-mail de aviso leva só nome, host e contagem — nunca a URL inteira nem o segredo', function (): void {
    $mail = new EndpointDisabledMail('Receptor de pedidos', 'hooks.example.com', 20, 'Conta Exemplo');
    $html = $mail->render();

    expect($html)->toContain('hooks.example.com')
        ->and($html)->toContain('Receptor de pedidos')
        ->and($html)->not->toContain('https://hooks.example.com/');
});
