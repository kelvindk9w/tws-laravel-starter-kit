<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Symfony\Component\HttpFoundation\IpUtils;
use Twstec\Kit\Webhooks\Enums\InboundMode;
use Twstec\Kit\Webhooks\Inbound\Contracts\EventParser;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundAuthorizationHandler;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundWebhookHandler;
use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\Verifiers\VerifierFactory;

/**
 * Uma FONTE de webhooks recebidos: o nome (o da URL —
 * `POST /<webhooks.inbound.path>/<nome>`), o verificador, o handler do
 * aplicativo, o parser e as opções. Opção nula = o padrão do pacote
 * (`webhooks.inbound.*`).
 *
 * Pela configuração (`webhooks.inbound_sources.<nome>` — vale com o
 * `config:cache`) ou pelo código (InboundWebhooks::register, num provider):
 *
 *     InboundWebhooks::register(new InboundSource(
 *         name: 'pagamentos',
 *         verifier: new SharedSecretHeader('x-token', [config('services.x.token')]),
 *         handler: PagamentosHandler::class,
 *         parser: new JsonEventParser(idPath: 'id', typePath: 'event'),
 *     ));
 *
 * O MODO (`mode`, Enums\InboundMode): `queue` (o padrão — o handler
 * `InboundWebhookHandler` roda na fila) ou `authorization` (o handler
 * `InboundAuthorizationHandler` responde na própria requisição, com o
 * orçamento de tempo `budgetMs`). Handler que não implementa o contrato do
 * modo, opção de fila (`storePayload`, `tries`, `backoff`, `queue`,
 * `connection`) no modo `authorization` e `budgetMs` no modo `queue` são
 * erro de configuração (503 na rota) — nunca ignorados em silêncio.
 */
final readonly class InboundSource
{
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    /**
     * @var list<string>
     */
    public array $allowedIps;

    /**
     * @param  class-string<InboundWebhookHandler|InboundAuthorizationHandler>|InboundWebhookHandler|InboundAuthorizationHandler  $handler  o contrato do modo
     * @param  list<string>  $allowedIps  IPs e faixas CIDR (IPv4 e IPv6); vazio = qualquer origem
     * @param  list<int>|null  $backoff  espera antes de cada nova tentativa do job, em segundos
     * @param  int|null  $budgetMs  só no modo `authorization`: o orçamento de tempo do handler, em milissegundos
     */
    public function __construct(
        public string $name,
        public Verifier $verifier,
        public string|InboundWebhookHandler|InboundAuthorizationHandler $handler,
        public EventParser $parser = new JsonEventParser,
        array $allowedIps = [],
        public ?int $maxBodyBytes = null,
        public ?bool $storePayload = null,
        public ?int $tries = null,
        public ?array $backoff = null,
        public ?string $queue = null,
        public ?string $connection = null,
        public ?int $rateLimitPerMinute = null,
        public InboundMode $mode = InboundMode::Queue,
        public ?int $budgetMs = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidSourceConfiguration("Nome de fonte inválido: use minúsculas, dígitos, - e _ (até 64), como na URL ({$name}).");
        }

        self::checkMode($name, $mode, $handler, [
            'store_payload' => $storePayload === true,
            'tries' => $tries !== null,
            'backoff' => $backoff !== null,
            'queue' => $queue !== null,
            'connection' => $connection !== null,
        ], $budgetMs);

        $ips = [];

        foreach ($allowedIps as $entry) {
            $entry = trim((string) $entry);

            if ($entry === '') {
                continue;
            }

            if (! self::validRange($entry)) {
                throw new InvalidSourceConfiguration("Fonte {$name}: faixa de IP inválida na allowlist ({$entry}).");
            }

            $ips[] = $entry;
        }

        $this->allowedIps = $ips;
    }

    /**
     * A fonte a partir da configuração (`webhooks.inbound_sources.<nome>`).
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $verifier = $config['verifier'] ?? null;
        $handler = $config['handler'] ?? null;
        $parser = $config['parser'] ?? null;
        $event = $config['event'] ?? [];
        $mode = $config['mode'] ?? null;

        if (! is_array($verifier)) {
            throw new InvalidSourceConfiguration("Fonte {$name}: falta o verifier.");
        }

        if (! is_string($handler) || $handler === '') {
            throw new InvalidSourceConfiguration("Fonte {$name}: falta o handler.");
        }

        if ($parser !== null && (! is_string($parser) || ! class_exists($parser) || ! is_subclass_of($parser, EventParser::class))) {
            throw new InvalidSourceConfiguration("Fonte {$name}: o parser precisa implementar ".EventParser::class.'.');
        }

        $mode = $mode === null || $mode === '' ? InboundMode::Queue : (is_string($mode) ? InboundMode::tryFrom($mode) : null);

        if ($mode === null) {
            throw new InvalidSourceConfiguration("Fonte {$name}: modo desconhecido; use ".implode(' ou ', array_map(static fn (InboundMode $case): string => $case->value, InboundMode::cases())).'.');
        }

        /** @var array<string, mixed> $eventOptions */
        $eventOptions = is_array($event) ? $event : [];

        /**
         * @var array<string, mixed> $verifier
         * @var class-string<InboundWebhookHandler|InboundAuthorizationHandler> $handler
         * @var class-string<EventParser>|null $parser
         */
        return new self(
            name: $name,
            verifier: VerifierFactory::make($verifier),
            handler: $handler,
            parser: self::parser($parser, $eventOptions),
            allowedIps: self::list($config['allowed_ips'] ?? []),
            maxBodyBytes: self::int($config['max_body_bytes'] ?? null),
            storePayload: isset($config['store_payload']) ? (bool) $config['store_payload'] : null,
            tries: self::int($config['tries'] ?? null),
            backoff: isset($config['backoff']) ? self::backoffList($config['backoff']) : null,
            queue: is_string($config['queue'] ?? null) && $config['queue'] !== '' ? $config['queue'] : null,
            connection: is_string($config['connection'] ?? null) && $config['connection'] !== '' ? $config['connection'] : null,
            rateLimitPerMinute: self::int($config['rate_limit_per_minute'] ?? null),
            mode: $mode,
            budgetMs: self::int($config['budget_ms'] ?? null),
        );
    }

    /**
     * O handler implementa o contrato do MODO; no modo `authorization`, nada
     * de opção de fila (o handler roda na requisição, sem nova tentativa — a
     * opção daria a impressão errada de que há); no modo `queue`, nada de
     * orçamento de tempo (ele é do handler na requisição).
     *
     * @param  array<string, bool>  $queueOptions  opção => está definida
     */
    private static function checkMode(string $name, InboundMode $mode, string|InboundWebhookHandler|InboundAuthorizationHandler $handler, array $queueOptions, ?int $budgetMs): void
    {
        $contract = $mode === InboundMode::Authorization ? InboundAuthorizationHandler::class : InboundWebhookHandler::class;
        $implements = is_string($handler)
            ? class_exists($handler) && is_subclass_of($handler, $contract)
            : $handler instanceof $contract;

        if (! $implements) {
            throw new InvalidSourceConfiguration($mode === InboundMode::Authorization
                ? "Fonte {$name}: no modo authorization, o handler precisa implementar {$contract}."
                : "Fonte {$name}: o handler precisa implementar {$contract}.");
        }

        $defined = array_keys(array_filter($queueOptions));

        if ($mode === InboundMode::Authorization && $defined !== []) {
            throw new InvalidSourceConfiguration("Fonte {$name}: no modo authorization o handler responde na requisição, sem fila e sem nova tentativa do kit; tire ".implode(', ', $defined).'.');
        }

        if ($mode === InboundMode::Queue && $budgetMs !== null) {
            throw new InvalidSourceConfiguration("Fonte {$name}: budget_ms só vale no modo authorization (o handler da fila tem o tempo máximo do job, webhooks.inbound.timeout).");
        }
    }

    /**
     * O parser da fonte: a classe do aplicativo (com `fromConfig` recebe as
     * opções de `event`) ou o JSON padrão.
     *
     * @param  class-string<EventParser>|null  $class
     * @param  array<string, mixed>  $options
     */
    private static function parser(?string $class, array $options): EventParser
    {
        if ($class === null) {
            return JsonEventParser::fromConfig($options);
        }

        $parser = method_exists($class, 'fromConfig') ? $class::fromConfig($options) : app()->make($class);

        if (! $parser instanceof EventParser) {
            throw new InvalidSourceConfiguration('O parser precisa implementar '.EventParser::class.'.');
        }

        return $parser;
    }

    /**
     * O handler da FILA. Fonte no modo `authorization` lança (o job nunca
     * chama o handler de autorização — ele responde só na requisição).
     *
     * @throws InvalidSourceConfiguration
     */
    public function handler(): InboundWebhookHandler
    {
        if ($this->mode !== InboundMode::Queue) {
            throw new InvalidSourceConfiguration("Fonte {$this->name}: está no modo {$this->mode->value}; o handler dela não roda na fila.");
        }

        $handler = is_string($this->handler) ? app()->make($this->handler) : $this->handler;

        if (! $handler instanceof InboundWebhookHandler) {
            throw new InvalidSourceConfiguration("Fonte {$this->name}: o handler precisa implementar ".InboundWebhookHandler::class.'.');
        }

        return $handler;
    }

    /**
     * O handler de AUTORIZAÇÃO (modo `authorization`), resolvido pelo
     * container a cada requisição. Fonte em outro modo lança.
     *
     * @throws InvalidSourceConfiguration
     */
    public function authorizationHandler(): InboundAuthorizationHandler
    {
        if ($this->mode !== InboundMode::Authorization) {
            throw new InvalidSourceConfiguration("Fonte {$this->name}: está no modo {$this->mode->value}; não há handler de autorização.");
        }

        $handler = is_string($this->handler) ? app()->make($this->handler) : $this->handler;

        if (! $handler instanceof InboundAuthorizationHandler) {
            throw new InvalidSourceConfiguration("Fonte {$this->name}: no modo authorization, o handler precisa implementar ".InboundAuthorizationHandler::class.'.');
        }

        return $handler;
    }

    public function authorizes(): bool
    {
        return $this->mode === InboundMode::Authorization;
    }

    /**
     * O orçamento de tempo do handler de autorização, em milissegundos: o da
     * fonte ou o do pacote (`webhooks.inbound.authorization_budget_ms`).
     */
    public function authorizationBudgetMs(): int
    {
        return max(1, $this->budgetMs ?? (int) config('webhooks.inbound.authorization_budget_ms', 2000));
    }

    public function permits(?string $ip): bool
    {
        if ($this->allowedIps === []) {
            return true;
        }

        // Sem endereço de origem não há como satisfazer uma allowlist.
        return $ip !== null && $ip !== '' && IpUtils::checkIp($ip, $this->allowedIps);
    }

    public function maxBodyBytes(): int
    {
        return max(1, $this->maxBodyBytes ?? (int) config('webhooks.inbound.max_body_bytes', 262144));
    }

    /**
     * Guardar o corpo (cifrado)? No modo `authorization`, nunca — nem com o
     * padrão do pacote ligado: o registro do recebimento fica sem o corpo.
     */
    public function storesPayload(): bool
    {
        if ($this->mode === InboundMode::Authorization) {
            return false;
        }

        return $this->storePayload ?? (bool) config('webhooks.inbound.store_payload', false);
    }

    public function tries(): int
    {
        return max(1, $this->tries ?? (int) config('webhooks.inbound.tries', 5));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->backoff ?? self::backoffList(config('webhooks.inbound.backoff', [10, 60, 300, 1800]));
    }

    public function queue(): ?string
    {
        $queue = $this->queue ?? config('webhooks.inbound.queue');

        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    public function connection(): ?string
    {
        $connection = $this->connection ?? config('webhooks.inbound.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public function rateLimitPerMinute(): int
    {
        return max(1, $this->rateLimitPerMinute ?? (int) config('webhooks.inbound.rate_limit_per_minute', 600));
    }

    /**
     * IP exato ou CIDR, IPv4 ou IPv6.
     */
    public static function validRange(string $entry): bool
    {
        [$address, $mask] = array_pad(explode('/', $entry, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($mask === null) {
            return true;
        }

        $max = str_contains((string) $address, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask >= 0 && (int) $mask <= $max;
    }

    /**
     * @return list<int>
     */
    public static function backoffList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) (is_scalar($value) ? $value : ''));
        $list = [];

        foreach ($items as $item) {
            if (is_numeric($item) && (int) $item >= 0) {
                $list[] = (int) $item;
            }
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) (is_scalar($value) ? $value : ''));

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim(is_scalar($item) ? (string) $item : ''),
            $items,
        ), static fn (string $item): bool => $item !== ''));
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
