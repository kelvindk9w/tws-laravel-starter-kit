<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use DateTimeInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;

/**
 * Põe uma tentativa na FILA — DEPOIS do commit de quem disparou, e sem nunca
 * perder a entrega se a fila falhar: a entrega já está no banco (outbox); se
 * o envio para a fila lançar, o motivo vai para o log, a marca de "na fila"
 * é desfeita e o `webhooks:dispatch-pending` a põe na fila na próxima
 * rodada. A falha da fila NUNCA sobe para quem disparou o evento (a mudança
 * de negócio dele já foi confirmada).
 *
 * `queued_at` marca quando foi posta, para o comando não repor o que ainda
 * está esperando na fila.
 */
final class DeliveryQueue
{
    public function push(WebhookDelivery $delivery, ?DateTimeInterface $at = null, bool $manual = false, int|string|null $requestedBy = null): void
    {
        $job = new DeliverWebhook((int) $delivery->getKey(), $manual, $requestedBy);

        $connection = config('webhooks.delivery.connection');
        $queue = config('webhooks.delivery.queue');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        if ($at !== null && Carbon::instance($at)->isFuture()) {
            $job->delay($at);
        }

        // Quem chama está no contexto da conta da entrega (ou em modo
        // sistema, no comando): o escopo da conta vale normalmente. Marcado
        // ANTES do envio: com a fila `sync`, o job roda dentro do dispatch e
        // a marca não pode chegar depois do resultado.
        WebhookDelivery::query()->whereKey($delivery->getKey())->update(['queued_at' => Carbon::now()]);

        $id = (int) $delivery->getKey();
        $uuid = $delivery->uuid;

        DB::afterCommit(static function () use ($job, $id, $uuid): void {
            try {
                app(Dispatcher::class)->dispatch($job);
            } catch (Throwable $exception) {
                Log::warning('webhooks.queue_unavailable', [
                    'delivery' => $uuid,
                    'exception' => $exception::class,
                ]);

                try {
                    Accounts::asSystem('webhooks:queue-failed', static fn () => WebhookDelivery::query()->whereKey($id)->update(['queued_at' => null]));
                } catch (Throwable) {
                    // O comando do outbox repõe depois do prazo de qualquer jeito.
                }
            }
        });
    }
}
