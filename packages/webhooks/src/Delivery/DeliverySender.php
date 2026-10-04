<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use CurlHandle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Foundation\Identifiers\UuidColumn;
use Twstec\Kit\Foundation\Logging\CorrelationId;
use Twstec\Kit\Foundation\Logging\Redactor;
use Twstec\Kit\Foundation\Tracing\Http\OutboundCorrelation;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\BlockedDestinationException;
use Twstec\Kit\Webhooks\Security\DestinationPolicy;
use Twstec\Kit\Webhooks\Security\IpRanges;
use Twstec\Kit\Webhooks\Security\ValidatedDestination;
use Twstec\Kit\Webhooks\Signing\Signature;

/**
 * UMA TENTATIVA de entrega — chamada pelo job (DeliverWebhook).
 *
 * 1. RESERVA a entrega no banco (UPDATE condicional: só uma tentativa por
 *    vez, mesmo com dois jobs para a mesma entrega — o reenvio manual e o
 *    comando do outbox, por exemplo). Quem não reserva, sai sem fazer nada.
 * 2. Confere o DESTINO DE NOVO, com o DNS resolvido AGORA
 *    (DestinationPolicy): rebinding, nome que passou a apontar para a rede
 *    interna, esquema que deixou de valer → tentativa `blocked`, nada sai.
 * 3. Monta o corpo e a ASSINATURA (Signing\Signature) com os segredos
 *    vigentes do endpoint (os dois, durante a convivência).
 * 4. Envia pelo cliente `Http` do Laravel (sai na trilha de saída
 *    `outbound_http_logs`, que nunca grava cabeçalho — a assinatura não vai —
 *    nem o corpo), com:
 *    - CONEXÃO NO IP CONFERIDO: `CURLOPT_RESOLVE host:porta:ip` — o cURL não
 *      resolve o nome de novo — e, antes do primeiro byte, a conferência do
 *      IP da conexão aberta (`CURLOPT_PREREQFUNCTION`): IP diferente do
 *      conferido, ou não permitido, aborta;
 *    - URL remontada das partes conferidas;
 *    - sem redirect (resposta 3xx é falha: "redirect não seguido");
 *    - sem proxy (nem o do ambiente: o proxy resolveria o nome);
 *    - só o esquema conferido (`protocols`);
 *    - conexão nova, sem reaproveitar (`CURLOPT_FRESH_CONNECT`,
 *      `CURLOPT_FORBID_REUSE`);
 *    - tempo curto para conectar e no total;
 *    - sem o cabeçalho de correlation id (é interno; o receptor recebe o id
 *      do evento).
 * 5. Grava a TENTATIVA (status, duração, trecho cortado e redigido da
 *    resposta, erro sem URL, correlation_id) e o novo estado da entrega:
 *    sucesso; nova tentativa marcada pelo backoff; ou falha final. O reenvio
 *    manual é UMA tentativa (sem novas automáticas).
 * 6. Atualiza a saúde do endpoint (EndpointHealth): zera ou soma as falhas
 *    seguidas — e desativa, com aviso, no limite.
 *
 * Nada aqui lança para o worker: qualquer erro vira tentativa falha.
 */
final class DeliverySender
{
    public function __construct(
        private readonly DestinationPolicy $policy,
        private readonly ResponseExcerpt $excerpt,
        private readonly EndpointHealth $health,
        private readonly DeliveryQueue $queue,
        private readonly Redactor $redactor,
    ) {}

    public function attempt(int $deliveryId, bool $manual = false, int|string|null $requestedBy = null): void
    {
        // Modo sistema SÓ para reservar e achar a conta da entrega (o job não
        // carrega a conta); o envio em si roda na conta dela.
        $delivery = Accounts::asSystem('webhooks:deliver', fn (): ?WebhookDelivery => $this->claim($deliveryId));

        if ($delivery === null) {
            return;
        }

        /** @var Account $account */
        $account = $delivery->account()->firstOrFail();

        Accounts::actingAs($account, fn () => $this->send($delivery, $manual, $requestedBy));
    }

    /**
     * A reserva: pendente/aguardando nova tentativa e vencida, ou "em
     * andamento" com o prazo estourado (processo que morreu).
     */
    private function claim(int $deliveryId): ?WebhookDelivery
    {
        $now = Carbon::now();

        $claimed = WebhookDelivery::query()
            ->whereKey($deliveryId)
            ->where(fn ($query) => $query
                ->where(fn ($open) => $open
                    ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value])
                    ->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now)))
                ->orWhere(fn ($stale) => $stale
                    ->where('status', DeliveryStatus::Delivering->value)
                    ->where('locked_until', '<', $now)))
            ->increment('attempts', 1, [
                'status' => DeliveryStatus::Delivering->value,
                'locked_until' => $now->copy()->addSeconds(max(30, (int) config('webhooks.delivery.lock_seconds', 120))),
                'last_attempt_at' => $now,
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return WebhookDelivery::query()->with(['endpoint', 'event'])->find($deliveryId);
    }

    private function send(WebhookDelivery $delivery, bool $manual, int|string|null $requestedBy): void
    {
        $endpoint = $delivery->endpoint;

        if (! $endpoint->isActive()) {
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed->value,
                'locked_until' => null,
                'next_attempt_at' => null,
                'attempts' => max(0, $delivery->attempts - 1),
                'error' => __('webhooks.errors.endpoint_disabled'),
            ])->save();

            return;
        }

        $secrets = $endpoint->signingSecrets();
        $started = hrtime(true);
        $destination = null;
        $response = null;
        $outcome = AttemptOutcome::Failed;
        $error = null;
        $excerpt = null;

        try {
            $destination = $this->policy->check($endpoint->url);
        } catch (BlockedDestinationException $blocked) {
            $outcome = AttemptOutcome::Blocked;
            $error = $blocked->reason.': '.$blocked->getMessage();

            Log::warning('webhooks.destination_blocked', [
                'endpoint' => $endpoint->uuid,
                'delivery' => $delivery->uuid,
                'tenant_uuid' => Accounts::current()?->uuid,
                'host' => $blocked->host,
                'address' => $blocked->address,
                'reason' => $blocked->reason,
            ]);
        }

        if ($destination !== null) {
            try {
                $response = $this->post($destination, $delivery, $secrets);
                $body = $response->toPsrResponse()->getBody();
                $body->rewind();
                $raw = ResponseExcerpt::read($body);
                $excerpt = $this->excerpt->redact($raw, $secrets);

                if ($response->successful()) {
                    $outcome = AttemptOutcome::Succeeded;
                } elseif ($response->redirect()) {
                    $error = __('webhooks.errors.redirect_not_followed', ['status' => $response->status()]);
                } else {
                    $error = __('webhooks.errors.http_status', ['status' => $response->status()]);
                }
            } catch (Throwable $exception) {
                $error = $this->describe($exception);
            } finally {
                try {
                    $response?->toPsrResponse()->getBody()->close();
                } catch (Throwable) {
                }
            }
        }

        $duration = intdiv(hrtime(true) - $started, 1_000_000);

        $this->record($delivery, $endpoint, $outcome, $response?->status(), $duration, $excerpt, $error, $destination, $manual, $requestedBy);
    }

    /**
     * @param  list<string>  $secrets
     */
    private function post(ValidatedDestination $destination, WebhookDelivery $delivery, #[\SensitiveParameter] array $secrets): Response
    {
        $event = $delivery->event;
        $timestamp = Carbon::now()->getTimestamp();
        $body = self::body($delivery);

        $curl = [
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_PREREQFUNCTION => $this->connectionCheck($destination),
        ];

        // Nome DNS: o cURL conecta no IP conferido, sem resolver de novo.
        // IP literal: não há o que resolver.
        if (! IpRanges::isIp($destination->hostForCurl())) {
            $curl[CURLOPT_RESOLVE] = [$destination->curlResolveEntry()];
        }

        return $this->client()
            ->withHeaders([
                Signature::ID_HEADER => $event->uuid,
                Signature::EVENT_HEADER => $event->type,
                Signature::HEADER => Signature::header($timestamp, $body, $secrets),
                'User-Agent' => 'TWS-Kit-Webhooks/2',
            ])
            ->withBody($body, 'application/json')
            ->withoutRedirecting()
            ->connectTimeout(max(0.5, (float) config('webhooks.destination.connect_timeout', 3)))
            ->timeout(max(1.0, (float) config('webhooks.destination.timeout', 10)))
            ->withOptions([
                OutboundCorrelation::FLAGS_OPTION => ['header' => false],
                'proxy' => '',
                'protocols' => [$destination->scheme],
                // O corpo da resposta: só o trecho que vai para o log.
                'sink' => new CappedSink(ResponseExcerpt::limit()),
                'curl' => $curl,
            ])
            ->post($destination->url);
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson();
    }

    /**
     * Antes de o cURL mandar o primeiro byte: a conexão foi para o IP
     * conferido (e ele continua permitido)? Senão, aborta.
     */
    private function connectionCheck(ValidatedDestination $destination): \Closure
    {
        $expected = IpRanges::normalize($destination->address);
        $policy = $this->policy;

        return static function (CurlHandle $handle, string $primaryIp, string $localIp, int $primaryPort, int $localPort) use ($expected, $policy, $destination): int {
            $connected = IpRanges::normalize($primaryIp);

            if ($connected === null || $connected !== $expected || $primaryPort !== $destination->port || ! $policy->addressAllowed($connected)) {
                return CURL_PREREQFUNC_ABORT;
            }

            return CURL_PREREQFUNC_OK;
        };
    }

    /**
     * O CORPO enviado (e assinado): o id do evento (para deduplicar), o tipo,
     * quando aconteceu, a conta e o projeto (uuid) e os dados.
     */
    public static function body(WebhookDelivery $delivery): string
    {
        $event = $delivery->event;

        return (string) json_encode([
            'id' => $event->uuid,
            'type' => $event->type,
            'created_at' => $event->occurred_at->toIso8601ZuluString(),
            'account' => Accounts::current()?->uuid,
            'project' => $event->project?->uuid,
            'data' => $event->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function record(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        AttemptOutcome $outcome,
        ?int $status,
        int $duration,
        ?string $excerpt,
        ?string $error,
        ?ValidatedDestination $destination,
        bool $manual,
        int|string|null $requestedBy,
    ): void {
        $now = Carbon::now();
        $correlationId = CorrelationId::current();
        $error = $error !== null ? Str::limit($error, 480, '…') : null;

        WebhookDeliveryAttempt::query()->create([
            'webhook_delivery_id' => $delivery->getKey(),
            'attempt' => $delivery->attempts,
            'manual' => $manual,
            'created_by' => $manual ? $requestedBy : null,
            'outcome' => $outcome->value,
            'response_status' => $status,
            'duration_ms' => $duration,
            'response_excerpt' => $excerpt,
            'error' => $error,
            'destination_ip' => $destination?->address,
            'correlation_id' => UuidColumn::isValid($correlationId) ? $correlationId : null,
        ]);

        $succeeded = $outcome === AttemptOutcome::Succeeded;
        $delay = (! $succeeded && ! $manual) ? RetrySchedule::delayAfter($delivery->attempts) : null;
        $next = $delay !== null ? $now->copy()->addSeconds($delay) : null;

        $delivery->forceFill([
            'status' => match (true) {
                $succeeded => DeliveryStatus::Succeeded->value,
                $next !== null => DeliveryStatus::Retrying->value,
                default => DeliveryStatus::Failed->value,
            },
            'locked_until' => null,
            'next_attempt_at' => $next,
            'delivered_at' => $succeeded ? $now : $delivery->delivered_at,
            'response_status' => $status,
            'duration_ms' => $duration,
            'error' => $error,
        ])->save();

        $succeeded ? $this->health->succeeded($endpoint) : $this->health->failed($endpoint);

        if ($next !== null && $endpoint->refresh()->isActive()) {
            $this->queue->push($delivery, $next);
        }
    }

    /**
     * Tipo do erro e mensagem redigida, SEM URL nem IP de destino (a mensagem
     * do cURL repete a URL).
     */
    private function describe(Throwable $exception): string
    {
        $message = (string) preg_replace('#\b[a-z][a-z0-9+.-]*://\S+#i', '[url]', $exception->getMessage());

        // CURLE_ABORTED_BY_CALLBACK (42): a conferência antes do primeiro byte
        // recusou a conexão.
        if (preg_match('/cURL error 42\b|aborted by (?:an application )?callback/i', $message) === 1) {
            return __('webhooks.errors.connection_mismatch');
        }

        return class_basename($exception).': '.$this->redactor->redactString($message);
    }
}
