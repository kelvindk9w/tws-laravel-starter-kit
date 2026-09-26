<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Twstec\Kit\Auth\Enums\VerificationPurpose;

// As props do Inertia vão para o navegador (no primeiro carregamento, dentro
// do HTML). NENHUMA credencial passa por elas: nem nas compartilhadas
// (HandleInertiaRequests::share), nem nas de cada tela. Esta suíte varre as
// props de TODAS as telas, por nome de chave e por valor.

/**
 * Chaves que nunca podem aparecer em lugar nenhum das props.
 */
const FORBIDDEN_PROP_KEYS = [
    'password', 'password_hash', 'transaction_password', 'transaction_password_set_at',
    'remember_token', 'two_factor_code', 'two_factor_secret', 'two_factor_recovery_codes',
    'code_hash', 'token_hash', 'secret', 'secret_key', 'secret_hash', 'api_key', 'pepper',
    'app_key', 'sensitive_action_token', 'id',
];

/**
 * As props da página (visita do Inertia: JSON), como lista de
 * [caminho, chave real, valor] — a chave REAL de cada nível (um nome de rota
 * como `password.request` é uma chave só, não dois níveis).
 *
 * @return list<array{0: string, 1: string, 2: mixed}>
 */
function walkProps(mixed $node, string $path = ''): array
{
    if (! is_array($node)) {
        return [];
    }

    $entries = [];

    foreach ($node as $key => $value) {
        $current = $path === '' ? (string) $key : $path.'.'.$key;
        $entries[] = [$current, (string) $key, $value];
        array_push($entries, ...walkProps($value, $current));
    }

    return $entries;
}

/**
 * Nenhuma chave proibida em nenhum nível, e nenhum dos valores secretos.
 *
 * @param  list<string>  $secrets
 * @param  list<string>  $allowedPaths
 */
function assertNoSecrets(TestResponse $response, array $secrets = [], array $allowedPaths = []): void
{
    $page = $response->json();

    expect($page)->toHaveKey('props');

    foreach (walkProps($page['props']) as [$path, $key, $value]) {
        // As traduções são textos de interface (ex.: o RÓTULO "Senha"), não
        // dados — as chaves delas têm nomes como `password`. O valor ainda é
        // varrido atrás dos segredos.
        $isTranslation = str_starts_with($path, 'translations.');

        if (! $isTranslation && ! in_array($path, $allowedPaths, true)) {
            expect(in_array(mb_strtolower($key), FORBIDDEN_PROP_KEYS, true))
                ->toBeFalse("chave proibida nas props: {$path}");
        }

        foreach ($secrets as $secret) {
            expect(is_string($value) && $secret !== '' && str_contains($value, $secret))
                ->toBeFalse("valor secreto nas props, em {$path}");
        }
    }
}

function secretsOf(User $user): array
{
    $raw = $user->getAttributes();

    return array_values(array_filter([
        (string) ($raw['password'] ?? ''),
        (string) ($raw['transaction_password'] ?? ''),
        (string) ($raw['remember_token'] ?? ''),
        (string) config('app.key'),
        (string) config('api_keys.hash_pepper'),
    ]));
}

beforeEach(function () {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 1000);
});

it('o usuário compartilhado é uma LISTA FECHADA de campos', function () {
    $user = User::factory()->create(['transaction_password' => 'Transacao123', 'remember_token' => 'lembrar-123']);

    $this->actingAs($user)->withHeaders(inertiaHeaders())->get('/dashboard')
        ->assertOk()
        ->assertJsonPath('props.auth.user.email', $user->email);

    $keys = array_keys($this->withHeaders(inertiaHeaders())->get('/dashboard')->json('props.auth.user'));
    sort($keys);

    expect($keys)->toBe([
        'avatarUrl', 'code', 'email', 'emailVerified', 'hasTransactionPassword',
        'locale', 'name', 'theme', 'twoFactorEnabled', 'uuid',
    ]);
});

it('as props compartilhadas trazem só o esperado', function () {
    $props = $this->actingAs(User::factory()->create())->withHeaders(inertiaHeaders())->get('/dashboard')->json('props');

    expect(array_keys($props))->toEqualCanonicalizing([
        'errors', 'app', 'auth', 'kit', 'navigation', 'routes', 'flash', 'translations', 'sidebarOpen', 'overview',
    ]);
});

it('nenhuma credencial nas props das telas do painel', function (string $url) {
    $user = User::factory()->create([
        'password' => 'LoginForte123',
        'transaction_password' => 'Transacao123',
        'remember_token' => 'lembrar-123',
        'two_factor_enabled_at' => now(),
    ]);

    $response = $this->actingAs($user)->withHeaders(inertiaHeaders())->get($url)->assertOk();

    assertNoSecrets($response, [...secretsOf($user->fresh()), 'Transacao123', 'LoginForte123', 'lembrar-123']);
})->with(['/dashboard', '/profile', '/notifications', '/settings/transaction-password']);

it('nenhuma credencial nas props das telas de visitante', function (string $url) {
    assertNoSecrets($this->withHeaders(inertiaHeaders())->get($url)->assertOk(), [(string) config('app.key')]);
})->with(['/', '/login', '/register', '/forgot-password']);

it('a tela de redefinição só leva o token do PRÓPRIO link (e nada mais)', function () {
    $response = $this->withHeaders(inertiaHeaders())->get('/reset-password/tok-do-link?email=a@example.com')->assertOk();

    assertNoSecrets($response, [(string) config('app.key')], allowedPaths: ['token']);
    expect($response->json('props.token'))->toBe('tok-do-link');
});

it('no segundo fator, nem o código nem o estado intermediário vão para a tela', function () {
    $user = User::factory()->create(['password' => 'LoginForte123', 'two_factor_enabled_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);
    $code = lastVerificationCode(VerificationPurpose::LoginChallenge);

    $response = $this->withHeaders(inertiaHeaders())->get('/two-factor-challenge')->assertOk();

    assertNoSecrets($response, [$code, ...secretsOf($user)]);
    expect(json_encode($response->json('props')))->not->toContain('two_factor_login');
});

it('ligar o segundo fator não devolve o token de ação sensível nem o código', function () {
    $user = User::factory()->create(['transaction_password' => 'Transacao123']);

    $this->actingAs($user)->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123']);
    $code = lastVerificationCode(VerificationPurpose::SensitiveAction);

    $response = $this->from('/profile')->withHeaders(inertiaHeaders())
        ->put('/profile/two-factor', ['code' => $code, 'enabled' => true]);

    $page = $this->withHeaders(inertiaHeaders())->get('/profile');

    foreach ([$response, $page] as $r) {
        expect((string) $r->getContent())->not->toContain($code);
    }

    assertNoSecrets($page, [$code, ...secretsOf($user->fresh())]);
    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();
});

it('o HTML da primeira carga não traz credencial (as props vão dentro dele)', function () {
    $user = User::factory()->create(['transaction_password' => 'Transacao123', 'remember_token' => 'lembrar-123']);

    $html = (string) $this->actingAs($user)->get('/profile')->assertOk()->getContent();

    foreach (secretsOf($user->fresh()) as $secret) {
        expect($html)->not->toContain($secret);
    }

    expect($html)->not->toContain('Transacao123')->and($html)->not->toContain('lembrar-123');
});
