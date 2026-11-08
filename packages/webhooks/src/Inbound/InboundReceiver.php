<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twstec\Kit\Foundation\Logging\CorrelationContext;
use Twstec\Kit\Foundation\Logging\Redactor;
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
 * 7. fila: o job leva o evento (cifrado); se a fila recusar, o registro é
 *    desfeito e a resposta é 503 — o provedor reenvia e nada se perde.
 *
 * Respostas: 202 (aceito, na fila) ou 200 (repetição, nada reprocessado).
 * Nenhum log desta classe leva corpo, cabeçalho ou segredo — e o contexto
 * ainda passa pelo Redactor do kit.
 */
final class InboundReceiver
{
    public function __construct(
        private readonly InboundSources $sources,
        private readonly Redactor $redactor,
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
            if (! $this->dispatch($source, $stored, $payload, $request)) {
                InboundWebhookEvent::query()->whereKey($stored->getKey())->where('uuid', $record->uuid)->delete();

                return $this->empty(Response::HTTP_SERVICE_UNAVAILABLE);
            }

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
            ->update(['status' => InboundStatus::Received->value, 'updated_at' => $now]) === 1;

        if ($retried) {
            if (! $this->dispatch($source, $stored, $payload, $request)) {
                InboundWebhookEvent::query()->whereKey($stored->getKey())->update(['status' => InboundStatus::Failed->value]);

                return $this->empty(Response::HTTP_SERVICE_UNAVAILABLE);
            }

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
     * @param  array<array-key, mixed>|null  $payload
     */
    private function dispatch(InboundSource $source, InboundWebhookEvent $record, ?array $payload, Request $request): bool
    {
        try {
            ProcessInboundWebhook::queueFor($source, $record, $payload);

            return true;
        } catch (Throwable $exception) {
            $this->log('error', 'webhooks.inbound.queue_unavailable', $request, $source->name, [
                'event' => $record->uuid,
                'exception' => $exception::class,
            ]);

            return false;
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
