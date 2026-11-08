<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;
use Twstec\Kit\Webhooks\Inbound\ProcessInboundWebhook;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// O PROCESSAMENTO na fila: o handler recebe o evento como objeto de valor,
// só verificado e não duplicado; o job é idempotente (reserva no banco),
// tenta de novo com o backoff da fonte e marca a falha de vez; a fila leva o
// evento cifrado; o corpo guardado (opcional) fica cifrado e sai pela
// retenção agendada.
// =============================================================================

beforeEach(function (): void {
    RecordingInboundHandler::reset();
    inboundSources();
});

it('o handler recebe o evento verificado como objeto de valor (fila sync)', function (): void {
    postInbound('token', inboundBody('evt_ok'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    expect(RecordingInboundHandler::$events)->toHaveCount(1);

    $event = RecordingInboundHandler::$events[0];
    $record = InboundWebhookEvent::query()->sole();

    expect($event)->toBeInstanceOf(InboundEvent::class)
        ->and($event->source)->toBe('token')
        ->and($event->id)->toBe('evt_ok')
        ->and($event->type)->toBe('PAYMENT_RECEIVED')
        ->and($event->occurredAt?->toIso8601String())->toBe('2026-11-07T13:15:00+00:00')
        ->and($event->payload['payment']['id'])->toBe('pay_123')
        ->and($event->get('payment.customer'))->toBe('Cliente MARCADOR-DO-CORPO-7f3a')
        ->and($event->receipt)->toBe($record->uuid)
        ->and($event->attempt)->toBe(1)
        ->and(print_r($event, true))->not->toContain('MARCADOR-DO-CORPO-7f3a')
        ->and($record->status)->toBe(InboundStatus::Processed)
        ->and($record->attempts)->toBe(1)
        ->and($record->processed_at)->not->toBeNull()
        ->and($record->last_error)->toBeNull();
});

it('só verificado e não duplicado chega ao handler', function (): void {
    postInbound('token', inboundBody('evt_x'), ['x-provedor-token' => 'tok_errado'])->assertStatus(401);
    postInbound('token', inboundBody('evt_y'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    postInbound('token', inboundBody('evt_y'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();
    postInbound('token', inboundBody('evt_y'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

    expect(array_map(fn (InboundEvent $e): string => $e->id, RecordingInboundHandler::$events))->toBe(['evt_y']);
});

it('o job é idempotente: rodar duas vezes chama o handler uma vez', function (): void {
    Queue::fake();
    postInbound('token', inboundBody('evt_dois'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('reservado por outro worker: não chama o handler e volta para a fila depois do prazo', function (): void {
    Queue::fake();
    postInbound('token', inboundBody('evt_res'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    InboundWebhookEvent::query()->update(['status' => InboundStatus::Processing->value, 'locked_until' => Carbon::now()->addSeconds(60)]);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();
    $job->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertReleased();
    expect(RecordingInboundHandler::$events)->toBe([]);

    // Reserva vencida (o worker morreu): o próximo pega.
    InboundWebhookEvent::query()->update(['locked_until' => Carbon::now()->subSecond()]);
    $retry = clone $job;
    $retry->withFakeQueueInteractions();
    app()->call([$retry, 'handle']);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('falha do handler: volta a `received` com a CLASSE da exceção (não a mensagem) e o job tenta de novo com o backoff da fonte', function (): void {
    inboundSources(['token' => ['tries' => 3, 'backoff' => [5, 30]]]);
    Queue::fake();
    postInbound('token', inboundBody('evt_falha', 'DADO-DO-EVENTO-55'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();

    expect($job->tries)->toBe(3)->and($job->backoff())->toBe([5, 30]);

    RecordingInboundHandler::$failures = 1;
    $logs = captureLogs();

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);

    $record = InboundWebhookEvent::query()->sole();

    expect($record->status)->toBe(InboundStatus::Received)
        ->and($record->attempts)->toBe(1)
        ->and($record->last_error)->toBe(RuntimeException::class)
        ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.handler_failed')->not->toContain('DADO-DO-EVENTO-55');

    // A nova tentativa do worker conclui.
    app()->call([$job, 'handle']);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(RecordingInboundHandler::$events[0]->attempt)->toBe(2)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed)
        ->and(InboundWebhookEvent::query()->sole()->last_error)->toBeNull();
});

it('tentativas esgotadas: `failed`; o reenvio do provedor (mesmo id) põe na fila de novo e processa', function (): void {
    Queue::fake();
    postInbound('token', inboundBody('evt_esg'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();
    RecordingInboundHandler::$failures = 1;

    try {
        app()->call([$job, 'handle']);
    } catch (RuntimeException $exception) {
        // O worker, sem tentativas restantes, chama o failed().
        $job->failed($exception);
    }

    expect(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Failed);

    postInbound('token', inboundBody('evt_esg'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202)->assertExactJson(['status' => 'accepted']);

    /** @var ProcessInboundWebhook $again */
    $again = Queue::pushed(ProcessInboundWebhook::class)->last();
    app()->call([$again, 'handle']);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(RecordingInboundHandler::$events[0]->attempt)->toBe(2)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('fila sync (desenvolvimento): o handler roda na requisição; se falhar, 503 e o registro é desfeito — o reenvio processa', function (): void {
    RecordingInboundHandler::$failures = 1;

    postInbound('token', inboundBody('evt_sync'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(503);

    expect(InboundWebhookEvent::query()->count())->toBe(0);

    postInbound('token', inboundBody('evt_sync'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('o failed() do job marca `failed` sem tocar no que está concluído', function (): void {
    Queue::fake();
    postInbound('token', inboundBody('evt_f1'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();
    $job->failed(new RuntimeException('detalhe com dado'));

    expect(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Failed)
        ->and(InboundWebhookEvent::query()->sole()->last_error)->toBe(RuntimeException::class);

    InboundWebhookEvent::query()->update(['status' => InboundStatus::Processed->value]);
    $job->failed(new RuntimeException('tarde'));

    expect(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('o job vai CIFRADO para a fila: o corpo não aparece no que a fila guarda', function (): void {
    expect(new ProcessInboundWebhook(1, 'token'))->toBeInstanceOf(ShouldBeEncrypted::class);

    config(['queue.default' => 'database']);
    $marcador = 'MARCADOR-NA-FILA-3d1e';

    postInbound('token', inboundBody('evt_fila', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    $payload = (string) DB::table('jobs')->value('payload');

    expect($payload)->not->toBe('')
        ->and($payload)->not->toContain($marcador)
        ->and($payload)->not->toContain('pay_123')
        ->and($payload)->not->toContain(INBOUND_TOKEN)
        ->and(RecordingInboundHandler::$events)->toBe([]);

    // E o worker, que tem a APP_KEY, lê e processa.
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(RecordingInboundHandler::$events[0]->get('payment.description'))->toBe($marcador);
});

it('store_payload: o corpo fica CIFRADO no banco e o job não o carrega', function (): void {
    inboundSources(['token' => ['store_payload' => true]]);
    Queue::fake();
    $marcador = 'MARCADOR-GUARDADO-8b2c';

    postInbound('token', inboundBody('evt_guarda', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    $cru = (string) DB::table('webhook_inbound_events')->value('payload');
    $record = InboundWebhookEvent::query()->sole();

    expect($cru)->not->toContain($marcador)
        ->and(json_decode(Crypt::decryptString($cru), true)['payment']['description'])->toBe($marcador)
        ->and($record->payload['payment']['description'] ?? null)->toBe($marcador)
        ->and($record->toArray())->not->toHaveKey('payload');

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();

    expect($job->payload)->toBeNull();

    app()->call([$job, 'handle']);

    expect(RecordingInboundHandler::$events[0]->get('payment.description'))->toBe($marcador);
});

it('retenção: o corpo guardado sai depois de payload_days; o registro, depois de days', function (): void {
    inboundSources(['token' => ['store_payload' => true]]);
    config(['webhooks.inbound.retention.days' => 30, 'webhooks.inbound.retention.payload_days' => 7]);

    $this->travelTo(Carbon::now()->subDays(40));
    postInbound('token', inboundBody('evt_velho'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    $this->travelBack();

    $this->travelTo(Carbon::now()->subDays(10));
    postInbound('token', inboundBody('evt_medio'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    $this->travelBack();

    postInbound('token', inboundBody('evt_novo'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    $this->artisan('webhooks:prune-inbound')->assertSuccessful();

    $restantes = InboundWebhookEvent::query()->orderBy('event_id')->get()->keyBy('event_id');

    expect($restantes->keys()->all())->toBe(['evt_medio', 'evt_novo'])
        ->and($restantes['evt_medio']->payload)->toBeNull()
        ->and($restantes['evt_novo']->payload)->not->toBeNull();

    // Fora da janela da deduplicação, o id volta a ser novo.
    postInbound('token', inboundBody('evt_velho'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
});

it('a retenção é agendada pelo pacote', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command)->implode("\n");

    expect($commands)->toContain('webhooks:prune-inbound');
});
