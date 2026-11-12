<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Illuminate\Support\Carbon;
use Throwable;
use Twstec\Kit\Webhooks\Enums\InboundDispatchOutcome;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;

/**
 * Põe um webhook recebido (já gravado) na fila, sem nunca perdê-lo (#120).
 *
 * - Deu certo: marca `queued_at`.
 * - A fila recusou (Redis fora, conexão inexistente): o registro continua
 *   `received` com `queued_at` nulo e `last_error = queue_unavailable`. Se a
 *   fonte não guarda o corpo, ele é guardado CIFRADO só para o reenvio à fila
 *   (o job apaga depois de processar; a retenção apaga de qualquer jeito).
 * - Fila síncrona com o handler falhando: o job já rodou (tentativas
 *   aumentaram) e o próprio job marcou `failed` — é falha do handler, não da
 *   fila; nada de guardar para reenviar.
 *
 * Usado pelo recebimento e pelo `webhooks:requeue-inbound`.
 */
final class InboundDispatcher
{
    public const string QUEUE_UNAVAILABLE = 'queue_unavailable';

    /**
     * Quanto um registro fora da fila espera antes de ser posto nela por
     * outro caminho (rodada agendada, reenvio do provedor): o recebimento
     * que o gravou ainda pode estar pondo na fila.
     */
    public static function requeueAfterSeconds(): int
    {
        return max(0, (int) config('webhooks.inbound.requeue.after_seconds', 60));
    }

    /**
     * @param  array<array-key, mixed>|null  $payload  o corpo decodificado, quando a fonte não o guarda no banco
     */
    public function dispatch(InboundSource $source, InboundWebhookEvent $record, ?array $payload): InboundDispatchOutcome
    {
        $attemptsBefore = (int) InboundWebhookEvent::query()->whereKey($record->getKey())->value('attempts');

        try {
            ProcessInboundWebhook::queueFor($source, $record, $payload);
        } catch (Throwable) {
            $current = InboundWebhookEvent::query()->find($record->getKey());

            if ($current === null) {
                return InboundDispatchOutcome::QueueUnavailable;
            }

            if ($current->attempts > $attemptsBefore) {
                InboundWebhookEvent::query()->whereKey($current->getKey())->update(['queued_at' => Carbon::now()]);

                return InboundDispatchOutcome::HandlerFailed;
            }

            $current->forceFill([
                'queued_at' => null,
                'last_error' => self::QUEUE_UNAVAILABLE,
            ]);

            if ($payload !== null && $current->payload === null) {
                $current->payload = $payload;
            }

            $current->save();

            return InboundDispatchOutcome::QueueUnavailable;
        }

        InboundWebhookEvent::query()->whereKey($record->getKey())->update(['queued_at' => Carbon::now()]);

        return InboundDispatchOutcome::Queued;
    }
}
