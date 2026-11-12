<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Twstec\Kit\Webhooks\Security\HostResolver;
use Twstec\Kit\Webhooks\Security\SystemHostResolver;
use Twstec\Kit\Webhooks\Tests\TestCase;
use Twstec\Kit\Webhooks\WebhooksServiceProvider;

it('os providers da suíte são os da descoberta automática', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe(TestCase::PACKAGE_PROVIDERS)
        ->and($composer['require'])->toHaveKeys(['twstec/kit-accounts', 'twstec/kit-auth', 'twstec/kit-foundation', 'ext-curl']);
});

it('traz a configuração padrão: HTTPS obrigatório, nenhuma rede privada liberada, backoff de 1 min a 12 h', function (): void {
    $this->bootWith([]);

    expect(config('webhooks.destination.require_https'))->toBeTrue()
        ->and(config('webhooks.destination.allowed_private_networks'))->toBe([])
        ->and(config('webhooks.destination.connect_timeout'))->toBe(3.0)
        ->and(config('webhooks.destination.timeout'))->toBe(10.0)
        ->and(config('webhooks.delivery.backoff'))->toBe([60, 300, 1800, 7200, 43200])
        ->and(config('webhooks.destination.relaxed_environments'))->toBe(['local', 'testing'])
        ->and(config('webhooks.delivery.disable_after_failures'))->toBe(20)
        ->and(config('webhooks.delivery.disable_after_hours'))->toBe(72)
        ->and(config('webhooks.delivery.response_excerpt_bytes'))->toBe(1024)
        ->and(config('webhooks.secret.max_overlap_minutes'))->toBe(10080)
        ->and(config('webhooks.prune.days'))->toBe(30)
        // Webhooks recebidos: janela de 5 min, corpo NÃO guardado, nenhuma fonte.
        ->and(config('webhooks.inbound.enabled'))->toBeTrue()
        ->and(config('webhooks.inbound.path'))->toBe('webhooks/inbound')
        ->and(config('webhooks.inbound.tolerance_seconds'))->toBe(300)
        ->and(config('webhooks.inbound.max_body_bytes'))->toBe(262144)
        ->and(config('webhooks.inbound.store_payload'))->toBeFalse()
        ->and(config('webhooks.inbound.backoff'))->toBe([10, 60, 300, 1800])
        ->and(config('webhooks.inbound.retention.days'))->toBe(30)
        ->and(config('webhooks.inbound.retention.payload_days'))->toBe(7)
        ->and(config('webhooks.inbound_sources'))->toBe([]);
});

it('roda a migration das quatro tabelas, a do endurecimento da entrega, a dos webhooks recebidos e a da espera pela fila', function (): void {
    $arquivos = array_map('basename', glob(dirname(__DIR__, 2).'/database/migrations/*.php'));

    expect($arquivos)->toBe(['2026_10_03_000001_create_webhook_tables.php', '2026_10_29_000001_harden_webhook_delivery.php', '2026_11_07_000001_create_webhook_inbound_events_table.php', '2026_11_13_000002_add_queued_at_to_webhook_inbound_events_table.php'])
        ->and(Schema::hasTable('webhook_inbound_events'))->toBeTrue()
        // Fora da fila (fila indisponível no recebimento): `queued_at` nulo.
        ->and(Schema::hasColumn('webhook_inbound_events', 'queued_at'))->toBeTrue()
        // A deduplicação é do banco: UNIQUE (fonte, id do evento).
        ->and(collect(Schema::getIndexes('webhook_inbound_events'))->first(fn (array $index): bool => $index['columns'] === ['source', 'event_id'])['unique'] ?? false)->toBeTrue()
        ->and(Schema::hasColumn('webhook_inbound_events', 'account_id'))->toBeFalse()
        ->and(Schema::hasTable('webhook_endpoints'))->toBeTrue()
        ->and(Schema::hasTable('webhook_events'))->toBeTrue()
        ->and(Schema::hasTable('webhook_deliveries'))->toBeTrue()
        ->and(Schema::hasTable('webhook_delivery_attempts'))->toBeTrue()
        ->and(Schema::hasColumns('webhook_endpoints', ['uuid', 'account_id', 'project_id', 'created_by', 'url', 'events', 'secret', 'previous_secret', 'previous_secret_expires_at', 'status', 'consecutive_failures', 'failing_since', 'url_changed_at']))->toBeTrue()
        ->and(Schema::hasIndex('webhook_deliveries', ['webhook_event_id']))->toBeTrue()
        ->and(Schema::hasIndex('webhook_events', ['created_at']))->toBeTrue()
        // O índice parcial das entregas em aberto (o que o outbox varre).
        ->and(collect(Schema::getIndexes('webhook_deliveries'))->pluck('name')->all())->toContain('webhook_deliveries_open_due_index');
});

it('registra os comandos e agenda o outbox e a limpeza', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('webhooks:dispatch-pending')
        ->and($commands)->toContain('webhooks:prune')
        ->and($commands)->toContain('webhooks:prune-inbound');

    $agendados = collect(app(Schedule::class)->events())->map(fn ($event): string => $event->expression.' '.$event->command)->implode("\n");

    expect($agendados)->toContain('* * * * *')
        ->and($agendados)->toContain('webhooks:dispatch-pending')
        ->and($agendados)->toContain('20 4 * * *')
        ->and($agendados)->toContain('webhooks:prune')
        ->and($agendados)->toContain('35 4 * * *')
        ->and($agendados)->toContain('webhooks:prune-inbound');
});

it('o resolvedor padrão é o do sistema (a suíte troca pelo de mentira)', function (): void {
    $this->app->forgetInstance(HostResolver::class);
    $this->app->offsetUnset(HostResolver::class);
    (new WebhooksServiceProvider($this->app))->register();

    expect(app(HostResolver::class))->toBeInstanceOf(SystemHostResolver::class);
});

it('a view do e-mail de aviso é do pacote', function (): void {
    expect(view()->exists('webhooks::mail.endpoint-disabled'))->toBeTrue();
});
