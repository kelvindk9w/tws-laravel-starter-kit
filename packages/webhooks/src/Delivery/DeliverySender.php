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
 * 5. Grava o novo estado da entrega — SÓ se a reserva ainda é desta
 *    tentativa (mesmo id, `delivering` e o mesmo número de tentativas; senão
 *    outro worker assumiu e o resultado vai só para o log) — e a TENTATIVA
 *    (status, duração, trecho cortado e redigido da resposta, erro sem URL,
 *    correlation_id): sucesso; nova tentativa marcada pelo backoff; ou falha
 *    final. O backoff conta só as tentativas AUTOMÁTICAS: o reenvio manual é
 *    uma tentativa a mais e, se falhar numa entrega que ainda não tinha
 *    chegado, não cancela as automáticas que faltavam (a próxima fica marcada
 *    pelo backoff); num reenvio do que já foi entregue, a falha é final.
 * 6. Atualiza a saúde do endpoint (EndpointHealth): zera ou soma as falhas
 *    seguidas — e desativa, com aviso, no limite. Endpoint desativado
 *    (antes ou durante a tentativa): a entrega fica PAUSADA, não falha.
 *
 * Nada aqui lança para o worker: qualquer erro — inclusive antes do envio
 * (segredo que não decifra, conta que sumiu) — vira tentativa falha
 * registrada, com o texto genérico para a tela e o detalhe só no log. Uma
 * entrega "em envio" cujo processo morreu e que já esgotou as tentativas é
 * encerrada como falha (abandonExhausted), nunca reservada para sempre.
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

        try {
            /** @var Account $account */
            $account = $delivery->account()->firstOrFail();

            Accounts::actingAs($account, fn () => $this->send($delivery, $manual, $requestedBy));
        } catch (Throwable $exception) {
            $this->crashed($delivery, $manual, $requestedBy, $exception);
        }
    }

    /**
     * O prazo da reserva, em segundos: o configurado, mas nunca menos que o
     * tempo máximo do job mais uma folga — senão outro worker reservaria a
     * entrega enquanto esta tentativa ainda está no ar.
     */
    public static function lockSeconds(): int
    {
        return max(30, (int) config('webhooks.delivery.lock_seconds', 120), DeliverWebhook::timeoutSeconds() + 30);
    }

    /**
     * Encerra como FALHA as entregas "em envio" com o prazo estourado (o
     * processo morreu) que já usaram todas as tentativas automáticas — em vez
     * de reservá-las de novo para sempre. Com `$deliveryId`, só aquela.
     *
     * @return int quantas foram encerradas
     */
    public static function abandonExhausted(?int $deliveryId = null): int
    {
        return Accounts::asSystem('webhooks:deliver', static function () use ($deliveryId): int {
            $stale = WebhookDelivery::query()
                ->when($deliveryId !== null, fn ($query) => $query->whereKey($deliveryId))
                ->where('status', DeliveryStatus::Delivering->value)
                ->where('locked_until', '<', Carbon::now())
                ->where('attempts', '>=', RetrySchedule::maxAttempts())
                ->get();

            $closed = 0;

            foreach ($stale as $delivery) {
                $changed = WebhookDelivery::query()
                    ->whereKey($delivery->getKey())
                    ->where('status', DeliveryStatus::Delivering->value)
                    ->where('attempts', $delivery->attempts)
                    ->update([
                        'status' => DeliveryStatus::Failed->value,
                        'locked_until' => null,
                        'next_attempt_at' => null,
                        'error' => __('webhooks.errors.abandoned'),
                    ]);

                if ($changed !== 1) {
                    continue;
                }

                WebhookDeliveryAttempt::query()->create([
                    'account_id' => $delivery->account_id,
                    'webhook_delivery_id' => $delivery->getKey(),
                    'attempt' => $delivery->attempts,
                    'manual' => false,
                    'outcome' => AttemptOutcome::Failed->value,
                    'error' => __('webhooks.errors.abandoned'),
                ]);

                Log::warning('webhooks.delivery_abandoned', ['delivery' => $delivery->uuid, 'attempts' => $delivery->attempts]);

                $closed++;
            }

            return $closed;
        });
    }

    /**
     * A reserva: pendente/aguardando nova tentativa e vencida, ou "em
     * andamento" com o prazo estourado (processo que morreu) e ainda com
     * tentativa sobrando.
     */
    private function claim(int $deliveryId): ?WebhookDelivery
    {
        self::abandonExhausted($deliveryId);

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
                'locked_until' => $now->copy()->addSeconds(self::lockSeconds()),
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

        // Desativado depois de a entrega ir para a fila: não sai, e não
        // gasta a tentativa — fica pausada até a reativação.
        if (! $endpoint->isActive()) {
            WebhookDelivery::query()
                ->whereKey($delivery->getKey())
                ->where('status', DeliveryStatus::Delivering->value)
                ->where('attempts', $delivery->attempts)
                ->update([
                    'status' => DeliveryStatus::Paused->value,
                    'locked_until' => null,
                    'next_attempt_at' => null,
                    'queued_at' => null,
                    'attempts' => max(0, $delivery->attempts - 1),
                    'error' => __('webhooks.errors.paused'),
                ]);

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
            // Para a tela, o texto genérico; o motivo exato fica no log.
            $error = $blocked->publicMessage();

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

        $this->record($delivery, $outcome, $response?->status(), $duration, $excerpt, $error, $destination, $manual, $requestedBy, true);
    }

    /**
     * Erro inesperado antes de a tentativa ser gravada (segredo ou corpo que
     * não decifram, conta que sumiu, falha do resolvedor...): vira tentativa
     * FALHA, com texto genérico (o detalhe vai só para o log), e segue o
     * backoff — sem contar para a saúde do endpoint (a culpa não é do
     * receptor). Se nem isso der para gravar, o log registra e a reserva
     * vence: o outbox reserva de novo, e abandonExhausted encerra quando as
     * tentativas acabarem.
     */
    private function crashed(WebhookDelivery $delivery, bool $manual, int|string|null $requestedBy, Throwable $exception): void
    {
        Log::error('webhooks.attempt_crashed', [
            'delivery' => $delivery->uuid,
            'attempt' => $delivery->attempts,
            'exception' => $exception::class,
            'message' => $this->redactor->redactString((string) preg_replace('#\b[a-z][a-z0-9+.-]*://\S+#i', '[url]', $exception->getMessage())),
        ]);

        try {
            Accounts::asSystem('webhooks:deliver', fn () => $this->record($delivery, AttemptOutcome::Failed, null, 0, null, __('webhooks.errors.internal'), null, $manual, $requestedBy, false));
        } catch (Throwable $again) {
            Log::error('webhooks.attempt_record_failed', [
                'delivery' => $delivery->uuid,
                'exception' => $again::class,
            ]);
        }
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
        AttemptOutcome $outcome,
        ?int $status,
        int $duration,
        ?string $excerpt,
        ?string $error,
        ?ValidatedDestination $destination,
        bool $manual,
        int|string|null $requestedBy,
        bool $countsForHealth,
    ): void {
        $now = Carbon::now();
        $correlationId = CorrelationId::current();
        $error = $error !== null ? Str::limit($error, 480, '…') : null;
        $succeeded = $outcome === AttemptOutcome::Succeeded;

        // O backoff conta só as tentativas automáticas (esta inclusa).
        $automatic = WebhookDeliveryAttempt::query()
            ->where('webhook_delivery_id', $delivery->getKey())
            ->where('manual', false)
            ->count() + ($manual ? 0 : 1);

        $next = null;

        // Manual que falhou numa entrega que ainda não tinha chegado: as
        // automáticas que faltavam continuam, a partir de agora. Entrega que
        // já tinha chegado (reenvio de algo entregue) não reabre automáticas.
        $owed = ! $manual || $delivery->delivered_at === null;

        if (! $succeeded && $owed && $automatic < RetrySchedule::maxAttempts()) {
            // Automática: a espera depois desta.
            $delay = RetrySchedule::delayAfter(max(1, $automatic));
            $next = $delay !== null ? $now->copy()->addSeconds($delay) : null;
        }

        // A reserva ainda é desta tentativa? Senão outro worker assumiu (o
        // prazo venceu): o resultado vai só para o log.
        $updated = WebhookDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', DeliveryStatus::Delivering->value)
            ->where('attempts', $delivery->attempts)
            ->update([
                'status' => match (true) {
                    $succeeded => DeliveryStatus::Succeeded->value,
                    $next !== null => DeliveryStatus::Retrying->value,
                    default => DeliveryStatus::Failed->value,
                },
                'locked_until' => null,
                'next_attempt_at' => $next,
                // A marca de "na fila" é refeita pelo push da nova tentativa;
                // sem push (erro interno), o outbox a põe na fila ao vencer.
                'queued_at' => null,
                'delivered_at' => $succeeded ? $now : $delivery->delivered_at,
                'response_status' => $status,
                'duration_ms' => $duration,
                'error' => $error,
            ]);

        if ($updated !== 1) {
            Log::warning('webhooks.reservation_lost', [
                'delivery' => $delivery->uuid,
                'attempt' => $delivery->attempts,
                'outcome' => $outcome->value,
            ]);

            return;
        }

        WebhookDeliveryAttempt::query()->create([
            'account_id' => $delivery->account_id,
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

        $endpoint = WebhookEndpoint::query()->find($delivery->webhook_endpoint_id);

        if ($endpoint === null) {
            return;
        }

        if ($countsForHealth) {
            $succeeded ? $this->health->succeeded($endpoint) : $this->health->failed($endpoint);
        }

        if ($next === null) {
            return;
        }

        if ($endpoint->refresh()->isActive()) {
            // Sem fila agora (erro interno, em modo sistema): o outbox põe na
            // fila quando vencer.
            if ($countsForHealth) {
                $this->queue->push($delivery, $next);
            }

            return;
        }

        // Desativado durante a tentativa: a nova tentativa espera a
        // reativação, pausada.
        WebhookDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', DeliveryStatus::Retrying->value)
            ->update([
                'status' => DeliveryStatus::Paused->value,
                'next_attempt_at' => null,
                'queued_at' => null,
                'error' => __('webhooks.errors.paused'),
            ]);
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
