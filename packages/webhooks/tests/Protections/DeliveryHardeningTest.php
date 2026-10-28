<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Actions\ResendDelivery;
use Twstec\Kit\Webhooks\Actions\SetEndpointStatus;
use Twstec\Kit\Webhooks\Actions\UpdateEndpoint;
use Twstec\Kit\Webhooks\Delivery\DeliverWebhook;
use Twstec\Kit\Webhooks\Delivery\DeliverySender;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Mail\EndpointDisabledMail;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// ENDURECIMENTO DA ENTREGA: desativação que não perde evento (pausa e
// retomada), erro inesperado que vira tentativa registrada, entrega travada
// que é encerrada, reenvio manual sem corrida e sem cancelar as automáticas,
// reserva conferida ao gravar, prazo da reserva acima do tempo do job e a
// marca de fila nas novas tentativas com atraso.
// =============================================================================

/**
 * @return Collection<int, WebhookDelivery>
 */
function hardenedDeliveries(): Collection
{
    return Accounts::asSystem('teste', fn () => WebhookDelivery::query()->with('attemptLog')->orderBy('id')->get());
}

function runDelivery(int $id, bool $manual = false): void
{
    (new DeliverWebhook($id, $manual))->handle(app(DeliverySender::class));
}

/**
 * @return ArrayObject<int, MessageLogged>
 */
function captureLog(): ArrayObject
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $logged->append($message));

    return $logged;
}

// --- 1. Desativação sem perder evento ---------------------------------------

it('uma queda CURTA não desativa, mesmo com muitas falhas seguidas: precisa também do prazo sem sucesso', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 500)]);
    config(['webhooks.delivery.disable_after_failures' => 3, 'webhooks.delivery.disable_after_hours' => 2]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);

    foreach (range(1, 5) as $n) {
        Webhooks::dispatch($account, 'order.created', ['n' => $n]);
    }

    $endpoint->refresh();

    expect($endpoint->status)->toBe(EndpointStatus::Active)
        ->and($endpoint->consecutive_failures)->toBe(5)
        ->and($endpoint->failing_since?->equalTo(Carbon::now()))->toBeTrue()
        ->and(hardenedDeliveries()->pluck('status')->unique()->all())->toBe([DeliveryStatus::Retrying]);
    Mail::assertNotQueued(EndpointDisabledMail::class);

    // Falhando sem parar há o prazo inteiro: aí sim, desativa.
    $this->travel(2)->hours();
    Webhooks::dispatch($account, 'order.created', ['n' => 6]);

    expect($endpoint->refresh()->status)->toBe(EndpointStatus::Disabled)
        ->and(hardenedDeliveries()->pluck('status')->unique()->all())->toBe([DeliveryStatus::Paused]);
    Mail::assertQueued(EndpointDisabledMail::class);
});

it('um sucesso no meio recomeça o prazo: o endpoint que se recupera não é desativado', function (): void {
    $this->freezeSecond();
    config(['webhooks.delivery.disable_after_failures' => 2, 'webhooks.delivery.disable_after_hours' => 1]);
    Http::fake(['*' => Http::sequence()->push('', 500)->push('', 200)->push('', 500)->push('', 500)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);

    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $this->travel(2)->hours();
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);

    expect($endpoint->refresh()->failing_since)->toBeNull();

    Webhooks::dispatch($account, 'order.created', ['n' => 3]);
    Webhooks::dispatch($account, 'order.created', ['n' => 4]);

    // Duas falhas seguidas, mas falhando só desde agora: continua ativo.
    expect($endpoint->refresh()->status)->toBe(EndpointStatus::Active)
        ->and($endpoint->consecutive_failures)->toBe(2);
});

it('DESATIVAR à mão pausa o que não saiu; o job que já estava na fila não envia; eventos do período ficam guardados', function (): void {
    Queue::fake();
    Http::fake(['*' => Http::response('', 200)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);

    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $this->inAccountOf($owner, fn () => app(SetEndpointStatus::class)->handle($owner, $endpoint->uuid, false));
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);

    // O job da primeira já estava na fila: roda e não envia.
    runDelivery(hardenedDeliveries()->first()->id);

    Http::assertNothingSent();
    expect(hardenedDeliveries()->pluck('status')->all())->toBe([DeliveryStatus::Paused, DeliveryStatus::Paused])
        ->and(hardenedDeliveries()->pluck('attempts')->all())->toBe([0, 0])
        ->and(hardenedDeliveries()->first()->error)->toBe(__('webhooks.errors.paused'));

    // O outbox não põe pausada na fila.
    $this->travel(1)->hours();
    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
    Queue::assertPushed(DeliverWebhook::class, 1);
});

it('REATIVAR devolve à fila as pausadas dentro da retenção; as mais velhas viram falha com o motivo', function (): void {
    $this->freezeSecond();
    config(['webhooks.delivery.disable_after_failures' => 2, 'webhooks.delivery.disable_after_hours' => 0, 'webhooks.prune.days' => 30]);
    Http::fake(['*' => Http::sequence()->push('', 500)->push('', 500)->push('', 200)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);

    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);
    expect($endpoint->refresh()->status)->toBe(EndpointStatus::Disabled);

    // Um mês depois, o endpoint ainda desativado recebe outro evento.
    $this->travel(31)->days();
    Webhooks::dispatch($account, 'order.created', ['n' => 3]);
    Http::assertSentCount(2);

    $this->inAccountOf($owner, fn () => app(SetEndpointStatus::class)->handle($owner, $endpoint->uuid, true));

    $deliveries = hardenedDeliveries();

    expect($deliveries->pluck('status')->all())->toBe([DeliveryStatus::Failed, DeliveryStatus::Failed, DeliveryStatus::Succeeded])
        ->and($deliveries[0]->error)->toBe(__('webhooks.errors.paused_expired'))
        ->and($endpoint->refresh()->status)->toBe(EndpointStatus::Active)
        ->and($endpoint->failing_since)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.enabled')->sole()->changes['resumed_deliveries']['after'])->toBe(1);
    Http::assertSentCount(3);
});

// --- 2. Erro inesperado e entrega travada -----------------------------------

it('erro ANTES do envio (segredo que não decifra) vira tentativa falha registrada, segue o backoff e não conta para a saúde', function (): void {
    $this->freezeSecond();
    $logged = captureLog();
    Http::fake();

    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    DB::table('webhook_endpoints')->where('id', $endpoint->id)->update(['secret' => 'nao-decifra']);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $delivery = hardenedDeliveries()->sole();

    Http::assertNothingSent();
    expect($delivery->status)->toBe(DeliveryStatus::Retrying)
        ->and($delivery->locked_until)->toBeNull()
        ->and((int) Carbon::now()->diffInSeconds($delivery->next_attempt_at))->toBe(60)
        ->and($delivery->attemptLog)->toHaveCount(1)
        ->and($delivery->attemptLog[0]->outcome)->toBe(AttemptOutcome::Failed)
        ->and($delivery->attemptLog[0]->error)->toBe(__('webhooks.errors.internal'))
        ->and($endpoint->refresh()->consecutive_failures)->toBe(0);

    $crash = collect($logged->getArrayCopy())->first(fn (MessageLogged $m): bool => $m->message === 'webhooks.attempt_crashed');

    expect($crash?->level)->toBe('error')
        ->and($crash?->context['delivery'])->toBe($delivery->uuid);
});

it('entrega "em envio" de processo morto que já usou todas as tentativas é ENCERRADA como falha, não reservada para sempre', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    Accounts::asSystem('teste', fn () => WebhookDelivery::query()->update([
        'status' => 'delivering',
        'locked_until' => now()->subSecond(),
        'attempts' => 6,
    ]));

    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();

    $delivery = hardenedDeliveries()->sole();

    Http::assertNothingSent();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->toBe(__('webhooks.errors.abandoned'))
        ->and($delivery->attemptLog->sole()->attempt)->toBe(6);

    // Com tentativa sobrando, a mesma situação é reservada de novo (e sai).
    Accounts::asSystem('teste', fn () => WebhookDelivery::query()->update([
        'status' => 'delivering',
        'locked_until' => now()->subSecond(),
        'attempts' => 2,
    ]));
    runDelivery($delivery->id);

    expect(hardenedDeliveries()->sole()->status)->toBe(DeliveryStatus::Succeeded);
    Http::assertSentCount(1);
});

// --- 3. Reenvio manual ------------------------------------------------------

it('reenvio que FALHA não cancela as tentativas automáticas que faltavam; esgotadas, a manual não cria outras', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 500)]);
    config(['webhooks.delivery.backoff' => [60]]);

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $delivery = hardenedDeliveries()->sole();
    expect($delivery->status)->toBe(DeliveryStatus::Retrying);

    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid));

    $delivery = hardenedDeliveries()->sole();

    // A automática que faltava continua marcada.
    expect($delivery->status)->toBe(DeliveryStatus::Retrying)
        ->and((int) Carbon::now()->diffInSeconds($delivery->next_attempt_at))->toBe(60)
        ->and($delivery->attemptLog->pluck('manual')->all())->toBe([false, true]);

    // A automática sai e esgota o backoff (2 automáticas)...
    $this->travel(61)->seconds();
    runDelivery($delivery->id);
    expect(hardenedDeliveries()->sole()->status)->toBe(DeliveryStatus::Failed);

    // ... e um reenvio que falha depois disso não cria automáticas novas.
    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid));

    expect(hardenedDeliveries()->sole()->status)->toBe(DeliveryStatus::Failed)
        ->and(hardenedDeliveries()->sole()->next_attempt_at)->toBeNull();
    Http::assertSentCount(4);
});

it('reenvio de entrega JÁ ENTREGUE que falha termina como falha, sem reabrir tentativas automáticas', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 200)->push('', 500)]);

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);
    $delivery = hardenedDeliveries()->sole();
    expect($delivery->status)->toBe(DeliveryStatus::Succeeded);

    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid));

    $after = hardenedDeliveries()->sole();

    expect($after->status)->toBe(DeliveryStatus::Failed)
        ->and($after->next_attempt_at)->toBeNull()
        ->and($after->delivered_at)->not->toBeNull();
    Http::assertSentCount(2);
});

it('reenvio não atropela um worker que reservou a entrega entre a leitura e a gravação', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);
    $delivery = hardenedDeliveries()->sole();

    // Assim que a Action lê a entrega, um worker a reserva.
    $raced = false;
    WebhookDelivery::retrieved(function (WebhookDelivery $read) use (&$raced): void {
        if ($raced) {
            return;
        }

        $raced = true;
        DB::table('webhook_deliveries')->where('id', $read->id)->update([
            'status' => 'delivering',
            'locked_until' => now()->addMinutes(2),
            'attempts' => $read->attempts + 1,
        ]);
    });

    expect(fn () => $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $delivery->uuid)))
        ->toThrow(ValidationException::class);

    $after = hardenedDeliveries()->sole();

    expect($after->status)->toBe(DeliveryStatus::Delivering)
        ->and($after->locked_until)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'webhook_delivery.resent')->where('outcome', 'success')->exists())->toBeFalse();
    Http::assertSentCount(1);
});

it('depois de trocar a URL, o reenvio recusa as entregas ANTERIORES à troca (e aceita as novas)', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 500)]);

    $owner = $this->owner();
    $account = $this->accountOf($owner);
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $antiga = hardenedDeliveries()->sole();

    $this->travel(1)->minutes();
    $token = $this->sensitiveToken($owner);
    $this->inAccountOf($owner, fn () => app(UpdateEndpoint::class)->handle($owner, $endpoint->uuid, [
        'name' => 'Receptor', 'url' => 'https://hooks.example.com/novo', 'events' => ['*'],
    ], $token));

    expect($endpoint->refresh()->url_changed_at?->equalTo(Carbon::now()))->toBeTrue();

    try {
        $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $antiga->uuid));
        $this->fail('O reenvio deveria ter sido recusado.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['delivery'][0])->toBe(__('webhooks.errors.url_changed'));
    }

    $this->travel(1)->minutes();
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);
    $nova = hardenedDeliveries()->last();

    $this->inAccountOf($owner, fn () => app(ResendDelivery::class)->handle($owner, $nova->uuid));

    expect(hardenedDeliveries()->last()->attemptLog->pluck('manual')->all())->toBe([false, true]);
});

// --- 4. Reserva conferida ao gravar e prazo da reserva ------------------------

it('o prazo da reserva nunca é menor que o tempo do job mais uma folga', function (): void {
    $this->freezeSecond();
    config(['webhooks.delivery.lock_seconds' => 30, 'webhooks.destination.timeout' => 60, 'webhooks.destination.connect_timeout' => 3]);

    expect(DeliverWebhook::timeoutSeconds())->toBe(93)
        ->and((new DeliverWebhook(1))->timeout)->toBe(93)
        ->and(DeliverySender::lockSeconds())->toBe(123);

    $lockedFor = null;
    Http::fake(function () use (&$lockedFor) {
        $lockedFor = (int) Carbon::now()->diffInSeconds(hardenedDeliveries()->sole()->locked_until);

        return Http::response('', 200);
    });

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    expect($lockedFor)->toBe(123);

    // Configurado acima do mínimo, vale o configurado.
    config(['webhooks.delivery.lock_seconds' => 600]);
    expect(DeliverySender::lockSeconds())->toBe(600);
});

it('se outro worker assumiu a entrega durante o envio, o resultado desta tentativa não sobrescreve o estado', function (): void {
    $logged = captureLog();

    Http::fake(function (Request $request) {
        // Durante o envio, o prazo venceu e outro worker reservou.
        DB::table('webhook_deliveries')->update(['attempts' => DB::raw('attempts + 1'), 'locked_until' => now()->addMinutes(2)]);

        return Http::response('', 500);
    });

    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $delivery = hardenedDeliveries()->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Delivering)
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->attemptLog)->toHaveCount(0)
        ->and($endpoint->refresh()->consecutive_failures)->toBe(0)
        ->and(collect($logged->getArrayCopy())->contains(fn (MessageLogged $m): bool => $m->message === 'webhooks.reservation_lost'))->toBeTrue();
});

// --- 5. Marca de fila nas novas tentativas com atraso ------------------------

it('nova tentativa com atraso marca a fila na hora em que vence: o outbox não põe job repetido antes disso', function (): void {
    $this->freezeSecond();
    Http::fake(['*' => Http::response('', 500)]);
    Queue::fake();
    config(['webhooks.delivery.backoff' => [1800]]);

    $owner = $this->owner();
    $this->createEndpoint($owner);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);
    runDelivery(hardenedDeliveries()->sole()->id);

    $delivery = hardenedDeliveries()->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Retrying)
        ->and($delivery->queued_at?->equalTo($delivery->next_attempt_at))->toBeTrue();
    // O disparo e a nova tentativa com atraso.
    Queue::assertPushed(DeliverWebhook::class, 2);

    // Vencida, mas o job atrasado ainda tem o prazo de reposição: nada novo.
    $this->travel(1800 + 299)->seconds();
    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
    Queue::assertPushed(DeliverWebhook::class, 2);

    // Passado o prazo, o job se perdeu: o outbox repõe.
    $this->travel(2)->seconds();
    $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
    Queue::assertPushed(DeliverWebhook::class, 3);

    expect(WebhookDeliveryAttempt::query()->withoutGlobalScopes()->count())->toBe(1);
});
