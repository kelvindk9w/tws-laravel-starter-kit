<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Webhooks\Delivery\DeliveryQueue;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;

/**
 * `webhooks:dispatch-pending` — o OUTBOX de volta à fila.
 *
 * Põe na fila as entregas vencidas que não estão esperando na fila: as que
 * nunca chegaram lá (a fila estava fora no disparo), as de nova tentativa
 * cujo job se perdeu e as "em andamento" cujo processo morreu (prazo
 * `locked_until` estourado). Uma entrega já posta na fila só volta depois de
 * `webhooks.outbox.requeue_after_seconds`. Dois jobs para a mesma entrega não
 * enviam duas vezes: a tentativa reserva a entrega no banco antes.
 *
 * Agendado pelo pacote (`webhooks.outbox.schedule`, padrão a cada minuto).
 */
final class DispatchPendingDeliveries extends Command
{
    protected $signature = 'webhooks:dispatch-pending {--limit=500 : Máximo de entregas por rodada}';

    protected $description = 'Põe de novo na fila as entregas de webhook vencidas (outbox)';

    public function handle(DeliveryQueue $queue): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $now = Carbon::now();
        $requeueBefore = $now->copy()->subSeconds(max(30, (int) config('webhooks.outbox.requeue_after_seconds', 300)));

        $count = Accounts::asSystem('webhooks:dispatch-pending', function () use ($queue, $limit, $now, $requeueBefore): int {
            $due = WebhookDelivery::query()
                ->where(fn ($query) => $query
                    ->where(fn ($open) => $open
                        ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value])
                        ->where(fn ($at) => $at->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
                        ->where(fn ($queued) => $queued->whereNull('queued_at')->orWhere('queued_at', '<=', $requeueBefore)))
                    ->orWhere(fn ($stale) => $stale
                        ->where('status', DeliveryStatus::Delivering->value)
                        ->where('locked_until', '<', $now)))
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($due as $delivery) {
                $queue->push($delivery);
            }

            return $due->count();
        });

        $this->components->info(__('webhooks.console.requeued', ['count' => $count]));

        return self::SUCCESS;
    }
}
