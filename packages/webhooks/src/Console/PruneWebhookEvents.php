<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEvent;

/**
 * `webhooks:prune` — RETENÇÃO: apaga os eventos mais velhos que
 * `webhooks.prune.days` (com as entregas e o log de tentativas deles), desde
 * que nenhuma entrega do evento esteja em aberto. Os corpos cifrados não
 * ficam guardados para sempre. Agendado pelo pacote (`webhooks.prune.schedule`).
 */
final class PruneWebhookEvents extends Command
{
    protected $signature = 'webhooks:prune {--days= : Idade mínima em dias (padrão: webhooks.prune.days)}';

    protected $description = 'Apaga os eventos de webhook antigos, com as entregas e o log deles';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('webhooks.prune.days', 30)));
        $cutoff = Carbon::now()->subDays($days);

        $total = Accounts::asSystem('webhooks:prune', function () use ($cutoff): int {
            $total = 0;

            do {
                $ids = WebhookEvent::query()
                    ->where('created_at', '<', $cutoff)
                    ->whereDoesntHave('deliveries', fn ($query) => $query->whereIn('status', [
                        DeliveryStatus::Pending->value,
                        DeliveryStatus::Delivering->value,
                        DeliveryStatus::Retrying->value,
                    ]))
                    ->orderBy('id')
                    ->limit(500)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                DB::transaction(function () use ($ids): void {
                    $deliveries = WebhookDelivery::query()->whereIn('webhook_event_id', $ids)->pluck('id');

                    WebhookDeliveryAttempt::query()->whereIn('webhook_delivery_id', $deliveries)->delete();
                    WebhookDelivery::query()->whereKey($deliveries)->delete();
                    WebhookEvent::query()->whereKey($ids)->delete();
                });

                $total += $ids->count();
            } while (true);

            return $total;
        });

        $this->components->info(__('webhooks.console.pruned', ['count' => $total]));

        return self::SUCCESS;
    }
}
