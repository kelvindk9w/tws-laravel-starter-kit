<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Http\Middleware\EnsureAccountIsActive;
use Twstec\Kit\Auth\Http\Middleware\EnsureEmailIsVerified;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Providers\AuthServiceProvider;
use Twstec\Kit\Auth\Support\PendingTwoFactorLogin;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// AS PROTEÇÕES VÊM DO PACOTE — numa aplicação Laravel limpa (Testbench), sem
// nada do starter: nenhum middleware no bootstrap, nenhum `throttle` nas rotas
// (ver TestCase::defineWebRoutes), nenhum provider do aplicativo. Se uma
// proteção daqui só funcionasse porque o starter lembrou de ligá-la, este
// arquivo reprovaria.
// =============================================================================

/**
 * Código de 6 dígitos do último VerificationCodeMail enviado.
 */
function ultimoCodigo(): string
{
    $codigo = null;

    Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$codigo): bool {
        $codigo = $mail->code;

        return true;
    });

    return (string) $codigo;
}

it('login: senha errada repetida bloqueia a conta+IP, e nem a senha certa entra durante o bloqueio', function (): void {
    // O limite de rota (throttle:sensitive, 5/min) fica alto aqui para provar
    // a SEGUNDA camada: o bloqueio da própria Action (auth.login).
    config(['security.rate_limit.sensitive' => 100, 'auth.login.max_attempts' => 3]);

    $user = User::fixture(['email' => 'alvo@example.com']);

    foreach (range(1, 3) as $tentativa) {
        $this->post('/login', ['email' => $user->email, 'password' => 'errada-'.$tentativa])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $bloqueio = $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta']);

    $bloqueio->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Muitas tentativas de login');
    $this->assertGuest();
});

it('login: o controller do pacote traz o próprio throttle:sensitive, sem a rota declarar nada', function (): void {
    config(['security.rate_limit.sensitive' => 2]);

    $this->post('/login', ['email' => 'x@example.com', 'password' => 'a'])->assertRedirect();
    $this->post('/login', ['email' => 'x@example.com', 'password' => 'b'])->assertRedirect();
    $this->post('/login', ['email' => 'x@example.com', 'password' => 'c'])->assertTooManyRequests();
});

it('conta bloqueada: o login recusa mesmo com a senha certa', function (): void {
    $user = User::fixture(['email' => 'bloqueada@example.com']);
    $user->forceFill(['status' => UserStatus::Blocked])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])
        ->assertSessionHasErrors(['email' => __('auth.account_inactive')]);

    $this->assertGuest();
});

it('conta bloqueada com sessão aberta perde a sessão na requisição seguinte (grupo web, pelo pacote)', function (): void {
    $user = User::fixture(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('painel');

    $user->forceFill(['status' => UserStatus::Blocked])->save();

    $this->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.account_inactive')]);

    $this->assertGuest();

    // E está no fim do grupo web, onde o pacote o pôs.
    $web = $this->app['router']->getMiddlewareGroups()['web'];
    expect(end($web))->toBe(EnsureAccountIsActive::class);
});

it('segundo fator ligado: a senha certa NÃO autentica — abre o desafio e manda o código', function (): void {
    Mail::fake();

    $user = User::fixture(['email_verified_at' => now()]);
    $user->forceFill(['two_factor_enabled_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session(PendingTwoFactorLogin::SESSION_KEY))->toBeArray();
    Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail): bool => $mail->purpose === VerificationPurpose::LoginChallenge);

    // O código certo conclui.
    $this->post('/two-factor-challenge', ['code' => ultimoCodigo()])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('código do segundo fator: erros demais bloqueiam a conta, e nem o código certo passa', function (): void {
    Mail::fake();
    config([
        'security.rate_limit.sensitive' => 100,
        'auth.two_factor.max_attempts_per_account' => 3,
        'auth.verification.max_attempts' => 10,
    ]);

    $user = User::fixture(['email_verified_at' => now()]);
    $user->forceFill(['two_factor_enabled_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta']);
    $certo = ultimoCodigo();
    $errado = $certo === '000000' ? '111111' : '000000';

    $this->post('/two-factor-challenge', ['code' => $errado])->assertSessionHasErrors(['code' => __('auth.two_factor.invalid')]);
    $this->post('/two-factor-challenge', ['code' => $errado])->assertSessionHasErrors(['code' => __('auth.two_factor.invalid')]);

    // O terceiro erro atinge o limite: o desafio é encerrado com o bloqueio.
    $bloqueio = $this->post('/two-factor-challenge', ['code' => $errado]);
    $bloqueio->assertRedirect(route('login'))->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Muitos códigos incorretos');

    // Senha certa de novo + código certo: continua bloqueado.
    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])->assertSessionHasErrors('email');
    $this->post('/two-factor-challenge', ['code' => $certo])->assertRedirect(route('login'));
    $this->assertGuest();
});

it('`verified` é o do pacote: conta sem e-mail confirmado não entra no painel, e a exigência é desligável', function (): void {
    $user = User::fixture(['email_verified_at' => null]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));

    expect($this->app['router']->getMiddleware()['verified'])->toBe(EnsureEmailIsVerified::class);

    // A regra do kit (AUTH_EMAIL_VERIFICATION_REQUIRED) — o `verified` do
    // framework não a conhece e continuaria barrando.
    config(['auth.email_verification.required' => false]);

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('`sensitive.token` é o do pacote: sem token de ação sensível, recusa', function (): void {
    $user = User::fixture(['email_verified_at' => now()]);

    $this->actingAs($user)->postJson('/acao-sensivel')->assertForbidden();
});

it('cadastro cria a conta pelo model configurado e manda o e-mail de verificação', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'nova@example.com')->firstOrFail();

    expect($user->isActive())->toBeTrue()
        ->and($user->codigo_publico)->toStartWith('USR-')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('opt-out explícito das proteções web: não instala e avisa no log a cada boot', function (): void {
    $this->bootWith(['auth.web_protections.enabled' => false]);

    // Uma requisição qualquer resolve o kernel HTTP (é quando o pacote
    // instalaria as proteções).
    $this->get('/login')->assertOk();

    $router = $this->app['router'];

    expect($router->getMiddlewareGroups()['web'])->not->toContain(EnsureAccountIsActive::class)
        ->and($router->getMiddleware()['verified'] ?? null)->not->toBe(EnsureEmailIsVerified::class)
        ->and($router->getMiddleware())->not->toHaveKey('sensitive.token');

    Log::spy();

    (new AuthServiceProvider($this->app))->boot();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_starts_with($message, 'AUTH_WEB_PROTECTIONS=false'));
});
