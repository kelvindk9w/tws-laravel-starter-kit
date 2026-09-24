<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\LoginResponse;
use Twstec\Kit\Auth\Http\Middleware\EnsureAccountIsActive;
use Twstec\Kit\Auth\Http\Middleware\EnsureEmailIsVerified;
use Twstec\Kit\Auth\Http\Middleware\RequiresSensitiveActionToken;
use Twstec\Kit\Auth\Providers\AuthServiceProvider;
use Twstec\Kit\Auth\Tests\TestCase;

it('os providers da suíte são os da descoberta automática', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe(TestCase::PACKAGE_PROVIDERS);
});

it('traz a configuração padrão das chaves do kit dentro de config(auth)', function (): void {
    expect(config('auth.login.max_attempts'))->toBe(5)
        ->and(config('auth.login.lockout_minutes'))->toBe(15)
        ->and(config('auth.two_factor.enabled'))->toBeTrue()
        ->and(config('auth.two_factor.max_attempts_per_account'))->toBe(10)
        ->and(config('auth.verification.max_attempts'))->toBe(5)
        ->and(config('auth.email_verification.required'))->toBeTrue()
        ->and(config('auth.password_rules.min_length'))->toBe(6)
        ->and(config('auth.web_protections.enabled'))->toBeTrue()
        // As do framework continuam lá.
        ->and(config('auth.defaults.guard'))->toBe('web');
});

it('roda as migrations com os mesmos nomes de arquivo que tinham no aplicativo', function (): void {
    $arquivos = array_map('basename', glob(dirname(__DIR__, 2).'/database/migrations/*.php'));

    expect($arquivos)->toBe([
        '2026_08_20_100000_extend_users_for_authentication.php',
        '2026_08_21_000003_add_locale_to_users_table.php',
        '2026_09_24_000001_mark_existing_users_email_as_verified.php',
        '2026_09_24_000002_add_two_factor_enabled_at_to_users_table.php',
    ])->and(app('migrator')->paths())->toContain(dirname(__DIR__, 2).'/database/migrations');
});

it('registra uma resposta padrão para cada contrato, sem passar por cima da do aplicativo', function (): void {
    foreach (AuthServiceProvider::RESPONSES as $contrato => $padrao) {
        expect(app($contrato))->toBeInstanceOf($padrao);
    }

    $propria = new class implements LoginResponse
    {
        public function toResponse(Request $request): Response
        {
            return response('própria');
        }
    };

    app()->instance(LoginResponse::class, $propria);
    (new AuthServiceProvider(app()))->register();

    expect(app(LoginResponse::class))->toBe($propria);
});

it('põe o status da conta no FIM do grupo web e instala os aliases do kit', function (): void {
    $kernel = app(Kernel::class);
    $web = $kernel->getMiddlewareGroups()['web'];

    expect(end($web))->toBe(EnsureAccountIsActive::class)
        ->and(array_count_values($web)[EnsureAccountIsActive::class])->toBe(1)
        ->and($kernel->getMiddlewareAliases()['verified'])->toBe(EnsureEmailIsVerified::class)
        ->and($kernel->getMiddlewareAliases()['sensitive.token'])->toBe(RequiresSensitiveActionToken::class);
});

it('um alias `verified` próprio do aplicativo prevalece sobre o do pacote', function (): void {
    $kernel = app(Kernel::class);
    $kernel->setMiddlewareAliases([...$kernel->getMiddlewareAliases(), 'verified' => 'App\\Middleware\\Proprio']);

    (new AuthServiceProvider(app()))->boot();

    expect($kernel->getMiddlewareAliases()['verified'])->toBe('App\\Middleware\\Proprio');
});
