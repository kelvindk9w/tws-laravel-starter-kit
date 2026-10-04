<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Testbench;
use Spatie\Backup\BackupServiceProvider;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\AccountsServiceProvider;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Providers\AuthServiceProvider;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Foundation\Audit\Providers\AuditServiceProvider;
use Twstec\Kit\Foundation\FoundationServiceProvider;
use Twstec\Kit\Foundation\Mail\Providers\MailServiceProvider;
use Twstec\Kit\Foundation\Settings\Providers\SettingsServiceProvider;
use Twstec\Kit\Webhooks\Actions\CreateEndpoint;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\HostResolver;
use Twstec\Kit\Webhooks\Tests\Fixtures\User;
use Twstec\Kit\Webhooks\Tests\Support\FakeResolver;
use Twstec\Kit\Webhooks\Tests\Support\Receiver;
use Twstec\Kit\Webhooks\WebhooksServiceProvider;

/**
 * Aplicação Laravel LIMPA — o esqueleto do Testbench, os pacotes foundation,
 * auth e accounts (dos quais este depende) e este pacote. Nada do starter.
 *
 * O DNS é o de mentira (FakeResolver): nenhum teste depende da rede. Os
 * envios de verdade vão para o RECEPTOR DE TESTE em 127.0.0.1 (o servidor
 * embutido do PHP), liberado pela lista de redes privadas de
 * desenvolvimento — a mesma configuração que um projeto usaria no Docker
 * de desenvolvimento.
 */
#[WithMigration]
abstract class TestCase extends Testbench
{
    use RefreshDatabase;

    /**
     * @var list<class-string>
     */
    public const PACKAGE_PROVIDERS = [
        WebhooksServiceProvider::class,
    ];

    /**
     * @var array<string, mixed>
     */
    public static array $scenario = [];

    public FakeResolver $dns;

    public ?Receiver $receiver = null;

    protected function getPackageProviders($app): array
    {
        return [
            BackupServiceProvider::class,
            AccountsServiceProvider::class,
            AuthServiceProvider::class,
            FoundationServiceProvider::class,
            AuditServiceProvider::class,
            MailServiceProvider::class,
            SettingsServiceProvider::class,
            ...self::PACKAGE_PROVIDERS,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('webhooks.events', ['order.created', 'order.shipped']);
        // Sem cooldown de reenvio do código de ação sensível (a regra é do auth).
        $app['config']->set('auth.verification.resend_cooldown_seconds', 0);
        // O backoff padrão, explícito (os testes de nova tentativa contam com ele).
        $app['config']->set('webhooks.delivery.backoff', [60, 300, 1800, 7200, 43200]);

        foreach (static::$scenario as $key => $value) {
            $app['config']->set($key, $value);
        }

        if (isset(static::$scenario['app.env'])) {
            $environment = (string) static::$scenario['app.env'];
            $app->detectEnvironment(static fn (): string => $environment);
        }

        $this->dns = new FakeResolver([
            'hooks.example.com' => ['93.184.215.14'],
        ]);
        $app->instance(HostResolver::class, $this->dns);
    }

    protected function tearDown(): void
    {
        $this->receiver?->stop();
        $this->receiver = null;

        parent::tearDown();
    }

    /**
     * Sobe uma aplicação nova com a configuração dada já valendo no boot.
     *
     * @param  array<string, mixed>  $config
     */
    protected function bootWith(array $config): void
    {
        static::$scenario = $config;

        try {
            $this->refreshApplication();
            $this->loadLaravelMigrations();
            $this->artisan('migrate', ['--force' => true]);
        } finally {
            static::$scenario = [];
        }
    }

    /**
     * O receptor de teste no ar, com http e 127.0.0.1 liberados (só fora de
     * produção — a regra do pacote).
     */
    public function receiver(): Receiver
    {
        config([
            'webhooks.destination.require_https' => false,
            'webhooks.destination.allowed_private_networks' => ['127.0.0.1/32'],
        ]);

        return $this->receiver ??= new Receiver;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function owner(array $attributes = []): User
    {
        return User::fixture(['email_verified_at' => now(), ...$attributes]);
    }

    public function accountOf(User $person): Account
    {
        return app(AccountService::class)->personalAccountOf($person) ?? throw new \LogicException('Pessoa sem conta pessoal.');
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function inAccountOf(User $person, \Closure $callback, ?Account $account = null): mixed
    {
        return Accounts::actingAs($account ?? $this->accountOf($person), $callback, $person);
    }

    /**
     * Token de ação sensível de verdade: senha de transação → código por
     * e-mail → token (o caminho da tela).
     */
    public function sensitiveToken(User $user): string
    {
        Mail::fake();

        if (! $user->hasTransactionPassword()) {
            $user->forceFill(['transaction_password' => 'Trans4cao!Segura'])->save();
        }

        $service = app(SensitiveActionService::class);
        $service->sendCode($user, 'Trans4cao!Segura');

        /** @var VerificationCodeMail $mail */
        $mail = Mail::queued(VerificationCodeMail::class)
            ->filter(fn (VerificationCodeMail $mail): bool => $mail->purpose === VerificationPurpose::SensitiveAction)
            ->last();

        return $service->confirmCode($user, $mail->code)['token'];
    }

    /**
     * Endpoint criado pela Action (papel, destino, ação sensível, trilha).
     *
     * @param  array<string, mixed>  $data
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function createEndpoint(User $owner, array $data = [], ?Account $account = null): array
    {
        $token = $this->sensitiveToken($owner);

        return $this->inAccountOf($owner, fn (): array => app(CreateEndpoint::class)->handle($owner, [
            'name' => 'Receptor de pedidos',
            'url' => 'https://hooks.example.com/webhooks',
            'events' => ['order.created'],
            ...$data,
        ], $token), $account);
    }
}
