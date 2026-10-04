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
        ->and(config('webhooks.delivery.disable_after_failures'))->toBe(20)
        ->and(config('webhooks.delivery.response_excerpt_bytes'))->toBe(1024)
        ->and(config('webhooks.secret.max_overlap_minutes'))->toBe(10080)
        ->and(config('webhooks.prune.days'))->toBe(30);
});

it('roda a migration das quatro tabelas', function (): void {
    $arquivos = array_map('basename', glob(dirname(__DIR__, 2).'/database/migrations/*.php'));

    expect($arquivos)->toBe(['2026_10_03_000001_create_webhook_tables.php'])
        ->and(Schema::hasTable('webhook_endpoints'))->toBeTrue()
        ->and(Schema::hasTable('webhook_events'))->toBeTrue()
        ->and(Schema::hasTable('webhook_deliveries'))->toBeTrue()
        ->and(Schema::hasTable('webhook_delivery_attempts'))->toBeTrue()
        ->and(Schema::hasColumns('webhook_endpoints', ['uuid', 'account_id', 'project_id', 'created_by', 'url', 'events', 'secret', 'previous_secret', 'previous_secret_expires_at', 'status', 'consecutive_failures']))->toBeTrue();
});

it('registra os comandos e agenda o outbox e a limpeza', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('webhooks:dispatch-pending')
        ->and($commands)->toContain('webhooks:prune');

    $agendados = collect(app(Schedule::class)->events())->map(fn ($event): string => $event->expression.' '.$event->command)->implode("\n");

    expect($agendados)->toContain('* * * * *')
        ->and($agendados)->toContain('webhooks:dispatch-pending')
        ->and($agendados)->toContain('20 4 * * *')
        ->and($agendados)->toContain('webhooks:prune');
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
