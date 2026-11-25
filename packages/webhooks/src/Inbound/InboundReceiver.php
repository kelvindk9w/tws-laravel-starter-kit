<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twstec\Kit\Foundation\Logging\CorrelationContext;
use Twstec\Kit\Foundation\Logging\Redactor;
use Twstec\Kit\Webhooks\Enums\InboundDispatchOutcome;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;

/**
 * O RECEBIMENTO de um webhook, na ordem — cada passo só roda se o anterior
 * passou:
 *
 * 1. fonte conhecida (senão 404) e bem configurada (senão 503, motivo no log);
 * 2. allowlist de IP da fonte, sobre o `ip()` do Laravel — que respeita os
 *    proxies confiáveis do kit (senão 403);
 * 3. limite de tamanho do corpo, pelo `Content-Length` declarado e pelo que
 *    chegou (senão 413);
 * 4. VERIFICAÇÃO sobre o corpo CRU, antes de qualquer decodificação (senão
 *    401, sem dizer o motivo — ele vai só para o log interno);
 * 5. leitura do evento (corpo ilegível: 400);
 * 6. DEDUPLICAÇÃO: INSERT com conflito ignorado sobre UNIQUE (fonte, id do
 *    evento) — sob corrida, o banco escolhe um; os outros são repetição;
 * 7. fila: o job leva o evento (cifrado). Aviso autêntico gravado é SEMPRE
 *    202 (#120): se a fila recusar, o registro fica `received` fora da fila
 *    e o `webhooks:requeue-inbound` agendado o põe lá depois — nunca é
 *    apagado nem vira 503 (provedores interrompem a fila do endereço depois
 *    de falhas seguidas). Com fila síncrona, a falha do handler leva o
 *    registro a `failed`, como na fila assíncrona.
 *
 * Respostas: 202 (aceito) ou 200 (repetição, nada reprocessado). Um reenvio
 * de aviso que ainda espera a fila tenta pôr na fila na hora.
 *
 * Fonte no modo `authorization` (Enums\InboundMode): os passos 1 a 5 são os
 * mesmos, com as mesmas recusas; depois, no lugar de 6 e 7, o handler de
 * autorização roda NA REQUISIÇÃO (sem fila) e a resposta dele — código e
 * corpo JSON — vai ao provedor sem interpretação. O registro do recebimento
 * é gravado antes (sem o corpo; a mesma chamada repetida soma `attempts` e
 * chega ao handler de novo — deduplicar é com ele). Exceção do handler:
 * 500 sem corpo. O tempo do handler vai para o log, com aviso acima do
 * orçamento.
 *
 * Nenhum log desta classe leva corpo, cabeçalho ou segredo — e o contexto
 * ainda passa pelo Redactor do kit.
 */
final class InboundReceiver
{
    public function __construct(
        private readonly InboundSources $sources,
        private readonly Redactor $redactor,
        private readonly InboundDispatcher $dispatcher,
    ) {}

    public function receive(Request $request, string $name): Response
    {
        try {
            $source = $this->sources->find($name);
        } catch (InvalidSourceConfiguration $exception) {
            $this->log('error', 'webhooks.inbound.misconfigured', $request, $name, ['problem' => $exception->getMessage()]);

            return $this->empty(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($source === null) {
            return $this->empty(Response::HTTP_NOT_FOUND);
        }

        if (! $source->permits($request->ip())) {
            $this->log('warning', 'webhooks.inbound.rejected', $request, $name, ['reason' => 'ip_not_allowed']);

            return $this->empty(Response::HTTP_FORBIDDEN);
        }

        $max = $source->maxBodyBytes();
        $declared = $request->headers->get('Content-Length');

        if ((is_numeric($declared) && (int) $declared > $max) || strlen($request->getContent()) > $max) {
            $this->log('warning', 'webhooks.inbound.rejected', $request, $name, ['reason' => 'body_too_large', 'max_bytes' => $max]);

            return $this->empty(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $now = Carbon::now();
        $inbound = InboundRequest::fromHttp($request, $now->getTimestamp());
        $verification = $source->verifier->verify($inbound);

        if (! $verification->passed) {
            $this->log('warning', 'webhooks.inbound.rejected', $request, $name, ['reason' => $verification->rejection?->value]);

            return $this->empty(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $parsed = $source->parser->parse($inbound, $verification);
        } catch (MalformedPayload $exception) {
            $this->log('warning', 'webhooks.inbound.malformed', $request, $name, ['problem' => $exception->getMessage()]);

            return $this->empty(Response::HTTP_BAD_REQUEST);
        }

        if ($source->authorizes()) {
            return $this->authorize($source, $inbound, $verification, $parsed, $now, $request);
        }

        $hash = hash('sha256', $inbound->body);
        $eventId = self::eventId($parsed->id ?? $verification->eventId, $hash);
        $type = $parsed->type === null ? null : mb_substr($parsed->type, 0, 150);

        $record = new InboundWebhookEvent;
        $record->forceFill([
            'uuid' => $record->newUniqueId(),
            'source' => $source->name,
            'event_id' => $eventId,
            'event_type' => $type,
            'occurred_at' => $parsed->occurredAt,
            'status' => InboundStatus::Received,
            'attempts' => 0,
            'payload' => $source->storesPayload() ? $parsed->payload : null,
            'payload_hash' => $hash,
            'received_at' => $now,
        ]);
        $record->setCreatedAt($now)->setUpdatedAt($now);

        // A deduplicação: o banco decide (ON CONFLICT DO NOTHING / INSERT OR
        // IGNORE sobre a unicidade fonte + id). Nada de "consulta e insere".
        $inserted = InboundWebhookEvent::query()->insertOrIgnore($record->getAttributes()) === 1;

        $stored = InboundWebhookEvent::query()
            ->where('source', $source->name)
            ->where('event_id', $eventId)
            ->first();

        if ($stored === null) {
            // Só se alguém apagou o registro no meio: o provedor reenvia.
            return $this->empty(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = $source->storesPayload() ? null : $parsed->payload;

        if ($inserted && $stored->uuid === $record->uuid) {
            $this->dispatch($source, $stored, $payload, $request);
            $this->log('info', 'webhooks.inbound.accepted', $request, $name, ['event' => $stored->uuid, 'type' => $type]);

            return $this->json('accepted', Response::HTTP_ACCEPTED);
        }

        if (! hash_equals($stored->payload_hash, $hash)) {
            // O mesmo id com outro corpo: o provedor reaproveitou o id ou
            // alguém está tentando passar outro conteúdo por um id conhecido.
            $this->log('warning', 'webhooks.inbound.duplicate_mismatch', $request, $name, ['event' => $stored->uuid]);
        }

        // Falhou de vez antes: o reenvio do provedor dá UMA nova chance (a
        // troca condicional deixa só um reenvio concorrente passar).
        $retried = $stored->status === InboundStatus::Failed && InboundWebhookEvent::query()
            ->whereKey($stored->getKey())
            ->where('status', InboundStatus::Failed->value)
            ->update(['status' => InboundStatus::Received->value, 'queued_at' => null, 'updated_at' => $now]) === 1;

        // Ainda esperando a fila (ela estava fora): o reenvio do provedor põe
        // na fila agora, sem esperar a rodada agendada. Só depois do mesmo
        // prazo da rodada — antes dele, o recebimento concorrente que gravou
        // o registro ainda vai pôr na fila. A troca condicional de
        // `queued_at` deixa só um (reenvio ou rodada) passar.
        $waitingSince = $now->copy()->subSeconds(InboundDispatcher::requeueAfterSeconds());
        $waiting = ! $retried && $stored->status === InboundStatus::Received && $stored->queued_at === null
            && InboundWebhookEvent::query()
                ->whereKey($stored->getKey())
                ->where('status', InboundStatus::Received->value)
                ->whereNull('queued_at')
                ->where('updated_at', '<=', $waitingSince)
                ->update(['queued_at' => $now, 'updated_at' => $now]) === 1;

        if ($retried || $waiting) {
            $this->dispatch($source, $stored, $payload, $request);
            $this->log('info', 'webhooks.inbound.requeued', $request, $name, ['event' => $stored->uuid]);

            return $this->json('accepted', Response::HTTP_ACCEPTED);
        }

        $this->log('info', 'webhooks.inbound.duplicate', $request, $name, ['event' => $stored->uuid]);

        return $this->json('duplicate', Response::HTTP_OK);
    }

    /**
     * O id que deduplica: o do evento (corpo ou esquema) ou, sem ele, o
     * hash do corpo. Id maior que a coluna vira o hash dele (prefixado).
     */
    public static function eventId(?string $id, string $bodyHash): string
    {
        if ($id === null || $id === '') {
            return 'sha256:'.$bodyHash;
        }

        return strlen($id) > 255 ? 'sha256:'.hash('sha256', $id) : $id;
    }

    /**
     * O modo `authorization`: grava o registro do recebimento (sem o corpo),
     * chama o handler na requisição e devolve a resposta dele, sem
     * interpretar. Nada de fila; nada de deduplicação (é com o handler).
     */
    private function authorize(InboundSource $source, InboundRequest $inbound, VerificationResult $verification, ParsedEvent $parsed, Carbon $now, Request $request): Response
    {
        $hash = hash('sha256', $inbound->body);
        $eventId = self::eventId($parsed->id ?? $verification->eventId, $hash);
        $type = $parsed->type === null ? null : mb_substr($parsed->type, 0, 150);

        // O registro do recebimento, SEM o corpo — nem com `store_payload`.
        // `processing` com `queued_at` e `locked_until` nulos: nenhuma rodada
        // nem job o pega (ele não espera a fila).
        $record = new InboundWebhookEvent;
        $record->forceFill([
            'uuid' => $record->newUniqueId(),
            'source' => $source->name,
            'event_id' => $eventId,
            'event_type' => $type,
            'occurred_at' => $parsed->occurredAt,
            'status' => InboundStatus::Processing,
            'attempts' => 0,
            'payload' => null,
            'payload_hash' => $hash,
            'received_at' => $now,
        ]);
        $record->setCreatedAt($now)->setUpdatedAt($now);

        InboundWebhookEvent::query()->insertOrIgnore($record->getAttributes());

        $stored = InboundWebhookEvent::query()
            ->where('source', $source->name)
            ->where('event_id', $eventId)
            ->first();

        if ($stored === null) {
            // Só se alguém apagou o registro no meio: o provedor tenta de novo.
            return $this->empty(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! hash_equals($stored->payload_hash, $hash)) {
            $this->log('warning', 'webhooks.inbound.duplicate_mismatch', $request, $source->name, ['event' => $stored->uuid]);
        }

        // Cada chamada que chega ao handler conta (a repetida também).
        InboundWebhookEvent::query()->whereKey($stored->getKey())->increment('attempts', 1, [
            'status' => InboundStatus::Processing->value,
            'updated_at' => $now,
        ]);

        $event = new InboundEvent(
            source: $stored->source,
            id: $stored->event_id,
            type: $type,
            occurredAt: $parsed->occurredAt,
            payload: $parsed->payload,
            receivedAt: CarbonImmutable::instance($now),
            receipt: $stored->uuid,
            attempt: $stored->attempts + 1,
        );

        $budget = $source->authorizationBudgetMs();
        $started = (int) hrtime(true);

        try {
            $answer = $source->authorizationHandler()->authorize($event);
            // Dentro do try: corpo que não vira JSON é falha do handler.
            $response = new JsonResponse($answer->body, $answer->status);
        } catch (Throwable $exception) {
            $elapsed = self::elapsedMs($started);

            InboundWebhookEvent::query()->whereKey($stored->getKey())->update([
                'status' => InboundStatus::Failed->value,
                'last_error' => mb_substr($exception::class, 0, 255),
                'updated_at' => Carbon::now(),
            ]);

            // Só a classe: a mensagem pode levar dado do evento. A resposta
            // não leva nem a classe.
            $this->log('warning', 'webhooks.inbound.authorization_failed', $request, $source->name, [
                'event' => $stored->uuid,
                'exception' => $exception::class,
                'duration_ms' => $elapsed,
            ]);
            $this->budget($elapsed, $budget, $request, $source->name, $stored->uuid);

            return $this->empty(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $elapsed = self::elapsedMs($started);

        InboundWebhookEvent::query()->whereKey($stored->getKey())->update([
            'status' => InboundStatus::Processed->value,
            'processed_at' => Carbon::now(),
            'last_error' => null,
            'updated_at' => Carbon::now(),
        ]);

        $this->log('info', 'webhooks.inbound.authorized', $request, $source->name, [
            'event' => $stored->uuid,
            'type' => $type,
            'status' => $answer->status,
            'duration_ms' => $elapsed,
            'budget_ms' => $budget,
        ]);
        $this->budget($elapsed, $budget, $request, $source->name, $stored->uuid);

        return $response;
    }

    /**
     * Acima do orçamento: aviso (nada é interrompido — o handler já voltou).
     */
    private function budget(int $elapsed, int $budget, Request $request, string $source, string $event): void
    {
        if ($elapsed > $budget) {
            $this->log('warning', 'webhooks.inbound.authorization_over_budget', $request, $source, [
                'event' => $event,
                'duration_ms' => $elapsed,
                'budget_ms' => $budget,
            ]);
        }
    }

    private static function elapsedMs(int $started): int
    {
        return intdiv((int) hrtime(true) - $started, 1_000_000);
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function dispatch(InboundSource $source, InboundWebhookEvent $record, ?array $payload, Request $request): void
    {
        $outcome = $this->dispatcher->dispatch($source, $record, $payload);

        if ($outcome === InboundDispatchOutcome::QueueUnavailable) {
            $this->log('error', 'webhooks.inbound.queue_unavailable', $request, $source->name, ['event' => $record->uuid]);
        } elseif ($outcome === InboundDispatchOutcome::HandlerFailed) {
            $this->log('warning', 'webhooks.inbound.handler_failed', $request, $source->name, ['event' => $record->uuid]);
        }
    }

    /**
     * @param  'info'|'warning'|'error'  $level
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, Request $request, string $source, array $context = []): void
    {
        Log::log($level, $message, $this->redactor->redactArray([
            'source' => $source,
            'ip' => $request->ip(),
            'correlation_id' => app(CorrelationContext::class)->current(),
            ...$context,
        ]));
    }

    private function empty(int $status): Response
    {
        return new Response('', $status);
    }

    private function json(string $status, int $code): JsonResponse
    {
        return new JsonResponse(['status' => $status], $code);
    }
}
