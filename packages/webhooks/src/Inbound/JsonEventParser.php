<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Carbon\CarbonImmutable;
use JsonException;
use Throwable;
use Twstec\Kit\Webhooks\Inbound\Contracts\EventParser;

/**
 * O parser padrão: corpo JSON (objeto na raiz), com id, tipo e data lidos de
 * caminhos da configuração `event` da fonte.
 *
 * Cada caminho é notação de ponto no corpo (`id`, `data.object.id`) ou
 * `header:<nome>` (um cabeçalho). Sem `id`, vale o id assinado pelo esquema
 * (ex.: `webhook-id`) ou, sem ele, o hash do corpo. A data aceita segundos
 * ou milissegundos Unix e texto de data (no fuso `timezone`, padrão UTC);
 * ilegível ou ausente, vale o carimbo assinado (ou nada).
 *
 *     'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated', 'timezone' => 'America/Sao_Paulo'],
 */
final readonly class JsonEventParser implements EventParser
{
    /**
     * Profundidade máxima do JSON aceito.
     */
    public const MAX_DEPTH = 64;

    public function __construct(
        private ?string $idPath = null,
        private ?string $typePath = 'type',
        private ?string $occurredAtPath = null,
        private string $timezone = 'UTC',
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromConfig(array $options): self
    {
        $path = static fn (string $key, ?string $default): ?string => array_key_exists($key, $options)
            ? (is_string($options[$key]) && $options[$key] !== '' ? $options[$key] : null)
            : $default;

        return new self(
            $path('id', null),
            $path('type', 'type'),
            $path('occurred_at', null),
            is_string($options['timezone'] ?? null) && $options['timezone'] !== '' ? $options['timezone'] : 'UTC',
        );
    }

    public function parse(InboundRequest $request, VerificationResult $verification): ParsedEvent
    {
        try {
            $payload = json_decode($request->body, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new MalformedPayload('O corpo não é JSON válido.');
        }

        if (! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new MalformedPayload('A raiz do JSON não é um objeto.');
        }

        $id = $this->scalar($request, $payload, $this->idPath);
        $type = $this->scalar($request, $payload, $this->typePath);
        $occurredAt = $this->date($this->rawValue($request, $payload, $this->occurredAtPath));

        if ($occurredAt === null && $verification->timestamp !== null) {
            $occurredAt = CarbonImmutable::createFromTimestampUTC($verification->timestamp);
        }

        return new ParsedEvent($id, $type, $occurredAt, $payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function rawValue(InboundRequest $request, array $payload, ?string $path): mixed
    {
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'header:')) {
            return $request->header(substr($path, 7));
        }

        return data_get($payload, $path);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function scalar(InboundRequest $request, array $payload, ?string $path): ?string
    {
        $value = $this->rawValue($request, $payload, $path);

        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) <= 16)) {
                $number = (int) $value;

                // Mais de 12 dígitos: milissegundos.
                return CarbonImmutable::createFromTimestampUTC($number > 99_999_999_999 ? intdiv($number, 1000) : $number);
            }

            if (is_string($value) && trim($value) !== '' && strlen($value) <= 64) {
                return CarbonImmutable::parse($value, $this->timezone)->utc();
            }
        } catch (Throwable) {
            // Data ilegível não derruba o evento: fica sem data.
        }

        return null;
    }
}
