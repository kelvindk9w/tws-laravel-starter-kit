<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Twstec\Kit\Webhooks\Enums\InboundDispatchOutcome;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Inbound\InboundDispatcher;
use Twstec\Kit\Webhooks\Inbound\InboundSources;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;

/**
 * `webhooks:requeue-inbound` — põe na fila os webhooks recebidos que ficaram
 * FORA dela (#120): gravados e respondidos com 202, mas a fila estava
 * indisponível na hora (`received` com `queued_at` nulo).
 *
 * - Só os parados há mais de `webhooks.inbound.requeue.after_seconds` (o
 *   recebimento em andamento ainda vai pôr o dele na fila).
 * - Cada registro é reservado por troca condicional de `queued_at`: duas
 *   rodadas (ou uma rodada e um reenvio do provedor) não põem o mesmo duas
 *   vezes — e o job é idempotente de qualquer jeito.
 * - Fila ainda fora: para na primeira recusa (sem martelar) e sai com falha;
 *   a próxima rodada tenta de novo.
 * - Fonte que saiu da configuração: o registro vai a `failed`.
 *
 * Agendado pelo pacote (`webhooks.inbound.requeue.schedule`).
 */
final class RequeueInboundWebhooks extends Command
{
    public const string UNKNOWN_SOURCE = 'unknown_source';

    protected $signature = 'webhooks:requeue-inbound
        {--after= : Idade mínima, em segundos, do registro fora da fila (padrão: webhooks.inbound.requeue.after_seconds)}
        {--limit= : Máximo de registros por rodada (padrão: webhooks.inbound.requeue.batch)}';

    protected $description = 'Põe na fila os webhooks recebidos que ficaram fora dela (fila indisponível no recebimento)';

    public function handle(InboundSources $sources, InboundDispatcher $dispatcher): int
    {
        $after = $this->option('after') === null ? InboundDispatcher::requeueAfterSeconds() : max(0, (int) $this->option('after'));
        $limit = max(1, (int) ($this->option('limit') ?? config('webhooks.inbound.requeue.batch', 100)));
        $now = Carbon::now();

        $records = InboundWebhookEvent::query()
            ->where('status', InboundStatus::Received->value)
            ->whereNull('queued_at')
            ->where('updated_at', '<=', $now->copy()->subSeconds($after))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $queued = 0;
        $unavailable = false;

        foreach ($records as $record) {
            $claimed = InboundWebhookEvent::query()
                ->whereKey($record->getKey())
                ->where('status', InboundStatus::Received->value)
                ->whereNull('queued_at')
                ->update(['queued_at' => $now, 'updated_at' => $now]) === 1;

            if (! $claimed) {
                continue;
            }

            $source = $sources->find($record->source);

            if ($source === null) {
                InboundWebhookEvent::query()->whereKey($record->getKey())->update([
                    'status' => InboundStatus::Failed->value,
                    'last_error' => self::UNKNOWN_SOURCE,
                    'updated_at' => $now,
                ]);
                Log::warning('webhooks.inbound.requeue_unknown_source', ['source' => $record->source, 'event' => $record->uuid]);

                continue;
            }

            $outcome = $dispatcher->dispatch($source, $record, null);

            if ($outcome === InboundDispatchOutcome::QueueUnavailable) {
                Log::error('webhooks.inbound.queue_unavailable', ['source' => $record->source, 'event' => $record->uuid, 'during' => 'requeue']);
                $unavailable = true;

                break;
            }

            $queued++;
        }

        $this->components->info(__('webhooks.console.inbound_requeued', ['count' => $queued]));

        if ($unavailable) {
            $this->components->error(__('webhooks.console.inbound_queue_unavailable'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
