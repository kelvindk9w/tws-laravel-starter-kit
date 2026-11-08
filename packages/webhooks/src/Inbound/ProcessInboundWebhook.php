<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;

/**
 * O JOB que entrega um webhook recebido ao handler do aplicativo.
 *
 * - Vai CIFRADO para a fila (ShouldBeEncrypted, APP_KEY): leva o id do
 *   registro e, quando a fonte não guarda o corpo no banco, o payload
 *   decodificado — nunca o corpo cru nem cabeçalho de assinatura.
 * - IDEMPOTENTE: antes de chamar o handler, reserva o registro com uma troca
 *   condicional (`received`/`failed` → `processing`, ou `processing` com
 *   prazo vencido). Registro já processado não chama o handler de novo;
 *   reservado por outro worker, o job volta para a fila depois do prazo.
 * - Falha do handler: o registro volta a `received` (com a classe da
 *   exceção, nunca a mensagem) e o job tenta de novo, com o backoff da fonte
 *   (`tries`, `backoff`). Esgotadas as tentativas: `failed` — e um reenvio
 *   do provedor com o mesmo id põe na fila de novo.
 */
final class ProcessInboundWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    /**
     * Tempo máximo do job, em segundos (webhooks.inbound.timeout).
     */
    public int $timeout;

    /**
     * @var list<int>
     */
    public array $backoffSeconds;

    /**
     * @param  array<array-key, mixed>|null  $payload  o corpo decodificado, quando a fonte não o guarda no banco
     * @param  list<int>  $backoff
     */
    public function __construct(
        public readonly int $receiptId,
        public readonly string $source,
        public readonly ?array $payload = null,
        int $tries = 5,
        array $backoff = [],
    ) {
        $this->tries = max(1, $tries);
        $this->backoffSeconds = array_map('intval', $backoff);
        $this->timeout = self::timeoutSeconds();
    }

    /**
     * Põe o job na fila da fonte (conexão e fila dela ou as do pacote).
     *
     * @param  array<array-key, mixed>|null  $payload
     */
    public static function queueFor(InboundSource $source, InboundWebhookEvent $record, ?array $payload): void
    {
        $job = new self((int) $record->getKey(), $source->name, $payload, $source->tries(), $source->backoff());

        if (($connection = $source->connection()) !== null) {
            $job->onConnection($connection);
        }

        if (($queue = $source->queue()) !== null) {
            $job->onQueue($queue);
        }

        app(Dispatcher::class)->dispatch($job);
    }

    /**
     * O tempo máximo do job (segundos) — e, com folga, o prazo da reserva.
     */
    public static function timeoutSeconds(): int
    {
        return max(1, (int) config('webhooks.inbound.timeout', 60));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->backoffSeconds;
    }

    public function handle(InboundSources $sources): void
    {
        // A fonte primeiro: sem ela (tirada da configuração), o job falha sem
        // reservar o registro.
        $source = $sources->find($this->source) ?? throw new InvalidSourceConfiguration("Fonte {$this->source} não existe mais.");

        $now = Carbon::now();
        $lock = $now->copy()->addSeconds(self::timeoutSeconds() + 30);

        // A reserva: só um worker passa daqui por vez.
        $claimed = InboundWebhookEvent::query()
            ->whereKey($this->receiptId)
            ->where(function ($query) use ($now): void {
                $query->whereIn('status', [InboundStatus::Received->value, InboundStatus::Failed->value])
                    ->orWhere(fn ($stale) => $stale->where('status', InboundStatus::Processing->value)->where('locked_until', '<', $now));
            })
            ->update([
                'status' => InboundStatus::Processing->value,
                'locked_until' => $lock,
                'updated_at' => $now,
            ]) === 1;

        $record = InboundWebhookEvent::query()->find($this->receiptId);

        if ($record === null || $record->status === InboundStatus::Processed) {
            return;
        }

        if (! $claimed) {
            // Outro worker está com ele: volta depois do prazo da reserva.
            $this->release(max(1, (int) $now->diffInSeconds($record->locked_until ?? $lock, true)) + 1);

            return;
        }

        InboundWebhookEvent::query()->whereKey($record->getKey())->increment('attempts');
        $attempt = $record->attempts + 1;

        $event = new InboundEvent(
            source: $record->source,
            id: $record->event_id,
            type: $record->event_type,
            occurredAt: $record->occurred_at === null ? null : CarbonImmutable::instance($record->occurred_at),
            payload: $this->payload ?? $record->payload ?? [],
            receivedAt: CarbonImmutable::instance($record->received_at),
            receipt: $record->uuid,
            attempt: $attempt,
        );

        try {
            $source->handler()->handle($event);
        } catch (Throwable $exception) {
            InboundWebhookEvent::query()->whereKey($record->getKey())->update([
                'status' => InboundStatus::Received->value,
                'locked_until' => null,
                'last_error' => mb_substr($exception::class, 0, 255),
                'updated_at' => Carbon::now(),
            ]);

            Log::warning('webhooks.inbound.handler_failed', [
                'source' => $record->source,
                'event' => $record->uuid,
                'attempt' => $attempt,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        InboundWebhookEvent::query()->whereKey($record->getKey())->update([
            'status' => InboundStatus::Processed->value,
            'processed_at' => Carbon::now(),
            'locked_until' => null,
            'last_error' => null,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Tentativas esgotadas (ou job que não roda mais): `failed`. Um reenvio
     * do provedor com o mesmo id põe na fila de novo.
     */
    public function failed(?Throwable $exception): void
    {
        // Só o que não está com outro worker (reserva vigente) nem concluído.
        $now = Carbon::now();

        InboundWebhookEvent::query()
            ->whereKey($this->receiptId)
            ->where(function ($query) use ($now): void {
                $query->where('status', InboundStatus::Received->value)
                    ->orWhere(fn ($stale) => $stale->where('status', InboundStatus::Processing->value)->where('locked_until', '<', $now));
            })
            ->update([
                'status' => InboundStatus::Failed->value,
                'locked_until' => null,
                'last_error' => $exception === null ? null : mb_substr($exception::class, 0, 255),
                'updated_at' => $now,
            ]);

        Log::error('webhooks.inbound.failed', [
            'source' => $this->source,
            'receipt_id' => $this->receiptId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
