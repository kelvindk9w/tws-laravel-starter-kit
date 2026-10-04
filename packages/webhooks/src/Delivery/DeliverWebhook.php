<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * O JOB de uma tentativa de entrega.
 *
 * Carrega SÓ identificadores (a entrega e, no reenvio manual, quem pediu) —
 * nunca o corpo do evento, a URL, o segredo ou a assinatura: tudo isso é lido
 * do banco (cifrado onde é sensível) na hora do envio. E o payload do job vai
 * CIFRADO para a fila (ShouldBeEncrypted, APP_KEY).
 *
 * Uma tentativa por job (`tries = 1`): as novas tentativas e o backoff são do
 * pacote (DeliverySender + RetrySchedule), não do worker — o estado fica no
 * banco e sobrevive a uma fila que cai (`webhooks:dispatch-pending`).
 */
final class DeliverWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $deliveryId,
        public readonly bool $manual = false,
        public readonly int|string|null $requestedBy = null,
    ) {
        $this->timeout = (int) ceil((float) config('webhooks.delivery.timeout', config('webhooks.destination.timeout', 10))) + 30;
    }

    public function handle(DeliverySender $sender): void
    {
        $sender->attempt($this->deliveryId, $this->manual, $this->requestedBy);
    }
}
