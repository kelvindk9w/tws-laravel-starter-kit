<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Inbound\ProcessInboundWebhook;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// O AVISO NÃO SE PERDE QUANDO A FILA CAI (#120).
//
// Aviso autêntico gravado é 202 mesmo com a fila fora; o registro espera
// (`received`, `queued_at` nulo) e o `webhooks:requeue-inbound` agendado o
// põe na fila quando ela volta — uma vez só, sem martelar a fila ainda fora.
// =============================================================================

beforeEach(function (): void {
    RecordingInboundHandler::reset();
    inboundSources();
});

/**
 * Recebe `$ids` com a fila fora do ar (conexão inexistente, fila de verdade).
 */
function receiveWithQueueDown(string ...$ids): void
{
    inboundSources(['token' => ['connection' => 'fila-que-nao-existe']]);

    foreach ($ids as $id) {
        postInbound('token', inboundBody($id), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    }
}

it('o que acabou de chegar não é pego: espera `after_seconds` (o recebimento em andamento põe o dele na fila)', function (): void {
    config(['webhooks.inbound.requeue.after_seconds' => 60]);
    receiveWithQueueDown('evt_novo');
    inboundSources();

    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();

    expect(RecordingInboundHandler::$events)->toBe([])
        ->and(InboundWebhookEvent::query()->sole()->queued_at)->toBeNull();

    $this->travel(61)->seconds();
    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Processed);
});

it('fila ainda fora: para na primeira recusa, sai com falha e todos continuam esperando', function (): void {
    receiveWithQueueDown('evt_a', 'evt_b', 'evt_c');
    $logs = captureLogs();
    $this->travel(2)->minutes();

    $this->artisan('webhooks:requeue-inbound')->assertFailed();

    $records = InboundWebhookEvent::query()->get();

    expect($records)->toHaveCount(3)
        ->and($records->every(fn ($r): bool => $r->status === InboundStatus::Received && $r->queued_at === null))->toBeTrue()
        ->and(substr_count(implode("\n", (array) $logs), 'webhooks.inbound.queue_unavailable'))->toBe(1);
});

it('só põe na fila quem espera a fila, um job por registro, e respeita o limite por rodada', function (): void {
    receiveWithQueueDown('evt_1', 'evt_2', 'evt_3');
    inboundSources();
    Queue::fake();

    postInbound('token', inboundBody('evt_ja_na_fila'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    Queue::fake(); // conta só o que a rodada põe na fila
    InboundWebhookEvent::query()->where('event_id', 'evt_3')->update(['status' => InboundStatus::Failed->value]);
    $this->travel(2)->minutes();

    $this->artisan('webhooks:requeue-inbound', ['--limit' => 1])->assertSuccessful();
    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();
    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();

    $pushed = Queue::pushed(ProcessInboundWebhook::class)->map(fn (ProcessInboundWebhook $job): int => $job->receiptId)->all();
    $ids = InboundWebhookEvent::query()->pluck('id', 'event_id');

    expect($pushed)->toBe([$ids['evt_1'], $ids['evt_2']])
        ->and(InboundWebhookEvent::query()->where('event_id', 'evt_3')->value('status'))->toBe(InboundStatus::Failed)
        ->and(InboundWebhookEvent::query()->whereNull('queued_at')->pluck('event_id')->all())->toBe(['evt_3']);
});

it('fonte que saiu da configuração: o registro vai a `failed`, sem tentar de novo a cada rodada', function (): void {
    receiveWithQueueDown('evt_orfao');
    config(['webhooks.inbound_sources' => []]);
    $this->travel(2)->minutes();

    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();

    $record = InboundWebhookEvent::query()->sole();

    expect($record->status)->toBe(InboundStatus::Failed)
        ->and($record->last_error)->toBe('unknown_source');
});

it('a retenção não apaga o corpo de quem ainda espera a fila', function (): void {
    config(['webhooks.inbound.retention.payload_days' => 0]);
    receiveWithQueueDown('evt_guardado');
    $this->travel(1)->days();

    $this->artisan('webhooks:prune-inbound')->assertSuccessful();

    expect(InboundWebhookEvent::query()->sole()->payload)->not->toBeNull();

    inboundSources();
    $this->artisan('webhooks:requeue-inbound')->assertSuccessful();

    expect(RecordingInboundHandler::$events)->toHaveCount(1)
        ->and(RecordingInboundHandler::$events[0]->id)->toBe('evt_guardado')
        ->and(RecordingInboundHandler::$events[0]->get('payment.id'))->toBe('pay_123');
});

it('a rodada é registrada e agendada a cada minuto pelo pacote', function (): void {
    expect(array_keys(Artisan::all()))->toContain('webhooks:requeue-inbound');

    $agendados = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'webhooks:requeue-inbound'));

    expect($agendados)->toHaveCount(1)
        ->and($agendados->first()->expression)->toBe('* * * * *');
});

it('migration: `queued_at` existe e os registros antigos contam como já postos na fila', function (): void {
    expect(Schema::hasColumn('webhook_inbound_events', 'queued_at'))->toBeTrue();

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_13_000002_add_queued_at_to_webhook_inbound_events_table.php';
    $migration->down();

    $now = Carbon::parse('2026-11-01 10:00:00');
    DB::table('webhook_inbound_events')->insert([
        'uuid' => (string) Str::uuid7(), 'source' => 'token', 'event_id' => 'evt_antigo', 'status' => 'received', 'attempts' => 0,
        'payload_hash' => str_repeat('a', 64), 'received_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $migration->up();

    expect(Carbon::parse((string) DB::table('webhook_inbound_events')->value('queued_at'))->equalTo($now))->toBeTrue();
});
