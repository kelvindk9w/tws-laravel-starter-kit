<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Account\Actions\CreateAccount;
use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Deletion\AccountDeletion;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Actions\ResendDelivery;
use Twstec\Kit\Webhooks\Actions\SendTestEvent;
use Twstec\Kit\Webhooks\Delivery\DeliverWebhook;
use Twstec\Kit\Webhooks\Delivery\DeliverySender;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// OUTBOX (evento durável antes da fila), IDEMPOTÊNCIA para o receptor,
// REENVIO MANUAL auditado e a EXCLUSÃO junto com a conta.
// =============================================================================

function allDeliveries(): Collection
{
    return Accounts::asSystem('teste', fn () => WebhookDelivery::query()->orderBy('id')->get());
}

it('evento fora do catálogo é recusado no disparo (erro de programação)', function (): void {
    $owner = $this->owner();

    expect(fn () => Webhooks::dispatch($this->accountOf($owner), 'invoice.unknown', []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Webhooks::dispatch($this->accountOf($owner), 'webhook.ping', []))->toThrow(InvalidArgumentException::class);
});

it('só vai para os endpoints ATIVOS da conta que ASSINAM o evento — e respeita o projeto', function (): void {
    Queue::fake();

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    $project = $this->inAccountOf($owner, fn () => Project::query()->create(['name' => 'Loja']));
    $other = $this->inAccountOf($owner, fn () => Project::query()->create(['name' => 'Outra']));

    $todos = $this->createEndpoint($owner, ['events' => ['*']])['endpoint'];
    $criados = $this->createEndpoint($owner, ['events' => ['order.created']])['endpoint'];
    $enviados = $this->createEndpoint($owner, ['events' => ['order.shipped']])['endpoint'];
    $doProjeto = $this->createEndpoint($owner, ['events' => ['*'], 'project' => $project->uuid])['endpoint'];
    $deOutroProjeto = $this->createEndpoint($owner, ['events' => ['*'], 'project' => $other->uuid])['endpoint'];
    $desativado = $this->createEndpoint($owner, ['events' => ['*']])['endpoint'];
    $desativado->forceFill(['status' => 'disabled'])->save();

    // Outra conta, mesmo evento: não recebe.
    $estranho = $this->owner();
    $this->createEndpoint($estranho, ['events' => ['*']]);

    Webhooks::dispatch($account, 'order.created', ['n' => 1], $project);

    expect(allDeliveries()->pluck('webhook_endpoint_id')->sort()->values()->all())
        ->toBe(collect([$todos->id, $criados->id, $doProjeto->id])->sort()->values()->all());

    // Sem projeto: o endpoint de projeto não recebe.
    Webhooks::dispatch($account, 'order.shipped', ['n' => 2]);

    expect(allDeliveries()->where('webhook_event_id', '!=', allDeliveries()->first()->webhook_event_id)->pluck('webhook_endpoint_id')->sort()->values()->all())
        ->toBe(collect([$todos->id, $enviados->id])->sort()->values()->all())
        ->and(Accounts::asSystem('teste', fn () => $deOutroProjeto->deliveries()->count()))->toBe(0);
});

it('sem endpoint que assine, nada é gravado', function (): void {
    $owner = $this->owner();

    expect(Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]))->toBeNull()
        ->and(DB::table('webhook_events')->count())->toBe(0);
});

it('OUTBOX: o evento é gravado na transação de quem disparou — desfeita a transação, não sobra evento nem envio', function (): void {
    Queue::fake();

    $owner = $this->owner();
    $this->createEndpoint($owner);

    try {
        DB::transaction(function () use ($owner): void {
            Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

            throw new RuntimeException('a mudança de negócio falhou');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('webhook_events')->count())->toBe(0)
        ->and(DB::table('webhook_deliveries')->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('OUTBOX: com a FILA FORA, o evento não se perde — o webhooks:dispatch-pending o põe na fila depois', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);

    // A fila "cai": a conexão dos webhooks aponta para um driver que não
    // existe (o dispatch lança, como lançaria com o Redis fora do ar).
    config([
        'queue.connections.fora-do-ar' => ['driver' => 'driver-que-nao-existe'],
        'webhooks.delivery.connection' => 'fora-do-ar',
    ]);

    $event = Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    expect($event)->not->toBeNull()
        ->and(allDeliveries()->sole()->status)->toBe(DeliveryStatus::Pending);

    Http::assertNothingSent();

    // A fila volta.
    config(['webhooks.delivery.connection' => null]);
    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();

    expect(allDeliveries()->sole()->status)->toBe(DeliveryStatus::Succeeded);
    Http::assertSentCount(1);
});

it('dois jobs para a mesma entrega não enviam duas vezes (a tentativa reserva no banco)', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $id = allDeliveries()->sole()->id;

    (new DeliverWebhook($id))->handle(app(DeliverySender::class));
    (new DeliverWebhook($id))->handle(app(DeliverySender::class));

    Http::assertSentCount(1);
    expect(allDeliveries()->sole()->attempts)->toBe(1);
});

it('entrega "em andamento" de um processo que morreu volta depois do prazo', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    Accounts::asSystem('teste', fn () => WebhookDelivery::query()->update(['status' => 'delivering', 'locked_until' => now()->addSeconds(120), 'attempts' => 1]));

    (new DeliverWebhook(allDeliveries()->sole()->id))->handle(app(DeliverySender::class));
    Http::assertNothingSent();

    $this->travel(121)->seconds();
    (new DeliverWebhook(allDeliveries()->sole()->id))->handle(app(DeliverySender::class));

    Http::assertSentCount(1);
    expect(allDeliveries()->sole()->status)->toBe(DeliveryStatus::Succeeded);
});

it('IDEMPOTÊNCIA: toda tentativa e todo reenvio levam o MESMO id de evento (corpo e cabeçalho)', function (): void {
    $this->freezeSecond();
    $owner = $this->owner();
    $receiver = $this->receiver();
    $this->dns->records['idem.invalid'] = ['127.0.0.1'];
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner, ['url' => $receiver->url('/fail', 'idem.invalid')]);
    $receiver->expectSecrets([$secret]);

    $event = Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $this->travel(61 + 301)->seconds();
    $this->artisan('webhooks:dispatch-pending');

    $delivery = allDeliveries()->sole();
    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid));

    $ids = array_map(fn (array $request): string => $request['headers']['x-webhook-id'].'|'.json_decode($request['body'], true)['id'], $receiver->requests());

    expect($ids)->toHaveCount(3)
        ->and(array_unique($ids))->toBe([$event->uuid.'|'.$event->uuid]);
});

it('REENVIO MANUAL: auditado, com quem pediu na tentativa, e uma tentativa só', function (): void {
    $owner = $this->owner();
    $receiver = $this->receiver();
    $this->dns->records['reenvio.invalid'] = ['127.0.0.1'];
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner, ['url' => $receiver->url('/ok', 'reenvio.invalid')]);
    $receiver->expectSecrets([$secret]);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);
    $delivery = allDeliveries()->sole();

    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid));

    $attempts = Accounts::asSystem('teste', fn () => WebhookDeliveryAttempt::query()->orderBy('attempt')->get());
    $row = AuditEvent::query()->where('action', 'webhook_delivery.resent')->sole();

    expect($attempts)->toHaveCount(2)
        ->and($attempts[1]->manual)->toBeTrue()
        ->and($attempts[1]->created_by)->toBe($owner->id)
        ->and($attempts[1]->attempt)->toBe(2)
        ->and($row->outcome->value)->toBe('success')
        ->and($row->actor_uuid)->toBe($owner->uuid)
        ->and($row->subject_type)->toBe('webhook_delivery')
        ->and($row->subject_uuid)->toBe($delivery->uuid)
        ->and($row->tenant_uuid)->toBe($this->accountOf($owner)->uuid)
        ->and($row->changes['endpoint']['after'])->toBe($endpoint->uuid)
        ->and(count($receiver->requests()))->toBe(2);
});

it('reenvio recusado (e registrado): membro sem papel, entrega de outra conta, endpoint desativado', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $delivery = allDeliveries()->sole();

    $member = $this->owner();
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => 'member']);

    expect(fn () => $this->inAccountOf($member, fn () => app(ResendDelivery::class)->handle($member, $delivery->uuid), $account))
        ->toThrow(AuthorizationException::class);

    $intruder = $this->owner();
    expect(fn () => $this->inAccountOf($intruder, fn () => app(ResendDelivery::class)->handle($intruder, $delivery->uuid)))
        ->toThrow(ModelNotFoundException::class);

    $endpoint->forceFill(['status' => 'disabled'])->save();
    expect(fn () => $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid)))
        ->toThrow(ValidationException::class);

    expect(AuditEvent::query()->where('action', 'webhook_delivery.resent')->where('outcome', 'denied')->count())->toBe(3);
});

it('ENVIAR TESTE: o ping vai só para aquele endpoint, assinado, e fica na trilha', function (): void {
    $owner = $this->owner();
    $receiver = $this->receiver();
    $this->dns->records['ping.invalid'] = ['127.0.0.1'];
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner, ['url' => $receiver->url('/ok', 'ping.invalid'), 'events' => ['order.shipped']]);
    $this->createEndpoint($owner, ['events' => ['*']]);
    $receiver->expectSecrets([$secret]);

    $event = $this->inAccountOf($owner, fn () => app(SendTestEvent::class)->handle($owner, $endpoint->uuid));

    expect($event->type)->toBe('webhook.ping')
        ->and($receiver->requests())->toHaveCount(1)
        ->and($receiver->requests()[0]['verified_with'])->toBe([0])
        ->and(allDeliveries())->toHaveCount(1)
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.test_sent')->where('subject_uuid', $endpoint->uuid)->exists())->toBeTrue();
});

it('EXCLUSÃO DA CONTA: endpoints, eventos, entregas e tentativas saem com ela; job atrasado não envia nada', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $owner = $this->owner();
    $account = $this->inAccountOf($owner, fn () => app(CreateAccount::class)->handle($owner, 'Empresa Exemplo'));

    $this->createEndpoint($owner, [], $account);
    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $deliveryId = allDeliveries()->sole()->id;

    $keeper = $this->owner();
    $this->createEndpoint($keeper);

    app(AccountDeletion::class)->deleteAccount($account, $owner);

    expect(Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->count()))->toBe(1)
        ->and(DB::table('webhook_events')->count())->toBe(0)
        ->and(DB::table('webhook_deliveries')->count())->toBe(0)
        ->and(DB::table('webhook_delivery_attempts')->count())->toBe(0);

    Http::fake();
    (new DeliverWebhook($deliveryId))->handle(app(DeliverySender::class));
    Http::assertNothingSent();
});

it('EXCLUSÃO DA PESSOA: o que ela criou nas contas que ficam perde o autor, e a conta pessoal leva os webhooks dela', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    $admin = $this->owner();
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $admin->id, 'role' => 'admin']);

    ['endpoint' => $endpoint] = $this->createEndpoint($admin, [], $account);
    $this->createEndpoint($admin);

    app(AccountDeletion::class)->deleteUser($admin);

    $remaining = Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->get());

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->id)->toBe($endpoint->id)
        ->and($remaining->first()->created_by)->toBeNull();
});
