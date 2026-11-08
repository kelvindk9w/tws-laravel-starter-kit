<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;

/**
 * `webhooks:prune-inbound` — RETENÇÃO dos webhooks recebidos:
 *
 * 1. apaga o corpo guardado (`store_payload`) dos registros mais velhos que
 *    `webhooks.inbound.retention.payload_days` — o registro de
 *    deduplicação continua;
 * 2. apaga os registros mais velhos que `webhooks.inbound.retention.days`
 *    (a janela da deduplicação: depois dela, o mesmo id volta a ser novo).
 *
 * Agendado pelo pacote (`webhooks.inbound.retention.schedule`).
 */
final class PruneInboundWebhooks extends Command
{
    protected $signature = 'webhooks:prune-inbound
        {--days= : Idade mínima, em dias, para apagar o registro (padrão: webhooks.inbound.retention.days)}
        {--payload-days= : Idade mínima, em dias, para apagar o corpo guardado (padrão: webhooks.inbound.retention.payload_days)}';

    protected $description = 'Apaga os webhooks recebidos antigos e o corpo guardado deles';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('webhooks.inbound.retention.days', 30)));
        $payloadDays = max(0, (int) ($this->option('payload-days') ?? config('webhooks.inbound.retention.payload_days', 7)));
        $now = Carbon::now();

        $purged = 0;

        do {
            $ids = InboundWebhookEvent::query()
                ->whereNotNull('payload')
                ->where('received_at', '<', $now->copy()->subDays($payloadDays))
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');

            $purged += $ids->isEmpty() ? 0 : InboundWebhookEvent::query()->whereKey($ids)->update(['payload' => null, 'updated_at' => $now]);
        } while ($ids->isNotEmpty());

        $deleted = 0;

        do {
            $ids = InboundWebhookEvent::query()
                ->where('received_at', '<', $now->copy()->subDays($days))
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');

            $deleted += $ids->isEmpty() ? 0 : (int) InboundWebhookEvent::query()->whereKey($ids)->delete();
        } while ($ids->isNotEmpty());

        $this->components->info(__('webhooks.console.inbound_pruned', ['count' => $deleted, 'payloads' => $purged]));

        return self::SUCCESS;
    }
}
