<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Travas de arquitetura do starter React.

/**
 * @return list<string>
 */
function reactSourceFiles(array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        if (is_dir(base_path($dir))) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('o starter React não usa a demonstração do kit', function () {
    foreach (reactSourceFiles(['app', 'bootstrap', 'config', 'routes', 'resources', 'database']) as $file) {
        $contents = (string) file_get_contents($file);

        expect(str_contains($contents, 'Twstec\\Kit\\Demo') || str_contains($contents, 'kit-demo'))
            ->toBeFalse("{$file} cita a demonstração");
    }

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys($composer['require'] + $composer['require-dev']))->not->toContain('twstec/kit-demo');
});

it('a autenticação é a do pacote: sem Fortify nem a do kit oficial', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys($composer['require'] + $composer['require-dev']))->not->toContain('laravel/fortify');

    foreach (reactSourceFiles(['app', 'bootstrap', 'config', 'routes']) as $file) {
        expect(str_contains((string) file_get_contents($file), 'Laravel\\Fortify'))->toBeFalse("{$file} cita o Fortify");
    }
});

it('os envios de autenticação são os controllers do pacote, com o throttle:sensitive', function (string $method, string $uri, string $controller) {
    /** @var RoutingRoute $route */
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array($method, $r->methods(), true) && $r->uri() === $uri);

    // A pilha FINAL da rota (o que o roteador executa, já sem o que a rota
    // tirou com withoutMiddleware) — não só a lista declarada.
    $resolved = collect(app('router')->gatherRouteMiddleware($route))
        ->map(fn (mixed $middleware): string => is_string($middleware) ? $middleware : get_debug_type($middleware));

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toStartWith('Twstec\\Kit\\Auth\\Http\\Controllers\\'.$controller)
        ->and($resolved->contains(fn (string $m): bool => str_contains($m, 'ThrottleRequests') && str_ends_with($m, ':sensitive')))
        ->toBeTrue("a rota {$method} {$uri} não passa pelo throttle:sensitive");
})->with([
    ['POST', 'login', 'AuthenticatedSessionController@store'],
    ['POST', 'register', 'RegisteredUserController@store'],
    ['POST', 'two-factor-challenge', 'TwoFactorChallengeController@store'],
    ['POST', 'two-factor-challenge/resend', 'TwoFactorChallengeController@resend'],
    ['POST', 'forgot-password', 'PasswordResetLinkController@store'],
    ['POST', 'reset-password', 'NewPasswordController@store'],
    ['POST', 'email/verification-notification', 'EmailVerificationController@resend'],
    ['GET', 'email/verify/{uuid}/{hash}', 'EmailVerificationController@verify'],
    ['PUT', 'settings/transaction-password', 'TransactionPasswordController@update'],
]);

it('as telas do painel exigem sessão e e-mail confirmado', function (string $uri) {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $r): bool => in_array('GET', $r->methods(), true) && $r->uri() === $uri);

    expect($route->gatherMiddleware())->toContain('auth')->toContain('verified');
})->with(['dashboard', 'profile', 'notifications', 'settings/transaction-password']);

it('o front não escreve URL da aplicação: usa o nome da rota', function () {
    foreach (reactSourceFiles(['resources/js']) as $file) {
        $contents = (string) file_get_contents($file);

        expect(preg_match('/(href|action|url)=\{?["\'`]\/[a-z]/i', $contents))->toBe(0, "{$file} escreve uma URL")
            ->and(preg_match('/(router\.(get|post|put|patch|delete|visit)|fetch)\(\s*["\'`]\//', $contents))->toBe(0, "{$file} escreve uma URL");
    }
});

it('o front não mostra texto em inglês fixo nos componentes (i18n)', function () {
    // Os textos que o kit oficial trazia fixos nos componentes do shadcn/ui.
    foreach (reactSourceFiles(['resources/js']) as $file) {
        $contents = (string) file_get_contents($file);

        foreach (['Toggle sidebar', '>Close<', '>More<', '"Loading"', 'Log in', 'Sign up', 'Forgot your password', 'Remember me', 'Hide password', 'Show password'] as $text) {
            expect(str_contains($contents, $text))->toBeFalse("{$file}: \"{$text}\"");
        }
    }
});
