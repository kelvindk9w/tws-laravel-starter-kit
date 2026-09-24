<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Testbench;
use Spatie\Backup\BackupServiceProvider;
use Twstec\Kit\Auth\Http\Controllers\AuthenticatedSessionController;
use Twstec\Kit\Auth\Http\Controllers\EmailVerificationController;
use Twstec\Kit\Auth\Http\Controllers\NewPasswordController;
use Twstec\Kit\Auth\Http\Controllers\PasswordResetLinkController;
use Twstec\Kit\Auth\Http\Controllers\RegisteredUserController;
use Twstec\Kit\Auth\Http\Controllers\SensitiveActionController;
use Twstec\Kit\Auth\Http\Controllers\TwoFactorChallengeController;
use Twstec\Kit\Auth\Providers\AuthServiceProvider;
use Twstec\Kit\Auth\Tests\Fixtures\User;
use Twstec\Kit\Foundation\Audit\Providers\AuditServiceProvider;
use Twstec\Kit\Foundation\FoundationServiceProvider;
use Twstec\Kit\Foundation\Mail\Providers\MailServiceProvider;
use Twstec\Kit\Foundation\Settings\Providers\SettingsServiceProvider;

/**
 * Aplicação Laravel LIMPA — o esqueleto do Testbench, o pacote foundation (do
 * qual este depende) e este pacote. Nada do starter: nenhuma view, nenhum
 * provider do aplicativo, nenhuma linha de bootstrap/app.php além da padrão.
 *
 * As rotas abaixo são o mínimo que um front declara: os POSTs apontam para os
 * controllers do pacote SEM nenhum middleware extra (o limite vem com o
 * controller) e as telas são respostas vazias com os nomes que as respostas
 * padrão usam. A proteção tem de vir do pacote, não daqui.
 */
#[WithMigration] // a tabela `users` do esqueleto Laravel; as colunas do kit vêm das migrations do pacote
abstract class TestCase extends Testbench
{
    use RefreshDatabase;

    /**
     * Providers do pacote — os mesmos que a descoberta automática instala
     * (composer.json → extra.laravel; um teste confere).
     *
     * @var list<class-string>
     */
    public const PACKAGE_PROVIDERS = [
        AuthServiceProvider::class,
    ];

    protected function getPackageProviders($app): array
    {
        // Numa aplicação, a descoberta segue a ordem do vendor: spatie,
        // twstec/kit-auth, twstec/kit-foundation (e os providers do módulo
        // de auditoria, e-mail e configurações da base).
        return [
            BackupServiceProvider::class,
            ...self::PACKAGE_PROVIDERS,
            FoundationServiceProvider::class,
            AuditServiceProvider::class,
            MailServiceProvider::class,
            SettingsServiceProvider::class,
        ];
    }

    /**
     * Configuração aplicada ANTES de os providers subirem (como num processo
     * de verdade) — ver bootWith().
     *
     * @var array<string, mixed>
     */
    public static array $scenario = [];

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('auth.providers.users.model', User::class);

        foreach (static::$scenario as $key => $value) {
            $app['config']->set($key, $value);
        }
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
        } finally {
            static::$scenario = [];
        }
    }

    /**
     * @param  Router  $router
     */
    protected function defineWebRoutes($router): void
    {
        $page = fn (string $name): string => $name;

        $router->middleware('guest')->group(function (Router $router) use ($page): void {
            $router->get('login', fn () => $page('tela de login'))->name('login');
            $router->post('login', [AuthenticatedSessionController::class, 'store']);
            $router->post('register', [RegisteredUserController::class, 'store']);
            $router->get('two-factor-challenge', fn () => $page('tela do código'))->name('two-factor.challenge');
            $router->post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);
            $router->post('two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend']);
            $router->post('forgot-password', [PasswordResetLinkController::class, 'store']);
            $router->get('reset-password/{token}', fn () => $page('tela de redefinição'))->name('password.reset');
            $router->post('reset-password', [NewPasswordController::class, 'store']);
        });

        $router->middleware('auth')->group(function (Router $router) use ($page): void {
            $router->post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
            $router->get('email/verify', fn () => $page('aviso de e-mail'))->name('verification.notice');
            $router->get('email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
            $router->post('sensitive-actions/code', [SensitiveActionController::class, 'store']);
        });

        $router->middleware(['auth', 'verified'])->group(function (Router $router) use ($page): void {
            $router->get('dashboard', fn () => $page('painel'))->name('dashboard');
            $router->post('acao-sensivel', fn () => $page('feito'))->middleware('sensitive.token');
        });
    }
}
