<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Twstec\Kit\Auth\Actions\ResetPassword;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Auth\Support\SessionFingerprint;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// TROCAR A SENHA (OU LIGAR O SEGUNDO FATOR) DERRUBA AS OUTRAS SESSÕES — numa
// aplicação limpa, sem nada do starter.
//
// A sessão de outro aparelho é simulada pela impressão que ela guardava
// (SessionFingerprint) antes da mudança: é exatamente o que uma sessão
// esquecida aberta no computador de outra pessoa teria.
// =============================================================================

beforeEach(function (): void {
    config(['security.rate_limit.sensitive' => 1000]);
});

function otherSessionsUser(array $attributes = []): User
{
    return User::fixture(['email_verified_at' => now(), ...$attributes]);
}

/**
 * Entra pelo formulário (sessão de verdade, com o id da conta guardado) e
 * abre uma página autenticada; devolve a impressão que esta sessão guarda.
 */
function signInAndOpenPanel(User $user, string $password = 'senha-correta'): mixed
{
    test()->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect();
    test()->get('/dashboard')->assertOk()->assertSee('painel');

    $fingerprint = session(SessionFingerprint::SESSION_KEY);

    expect($fingerprint)->toBeArray();

    return $fingerprint;
}

it('redefinição por e-mail encerra a sessão aberta e invalida o "lembrar de mim"', function (): void {
    $user = otherSessionsUser();
    signInAndOpenPanel($user);
    $rememberToken = $user->fresh()?->getRememberToken();

    // A redefinição acontece em outro lugar (outro navegador, sem ninguém
    // logado): o guard desta requisição não conhece a conta.
    Auth::forgetGuards();

    $status = app(ResetPassword::class)->handle([
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'senha-redefinida-1',
    ]);
    expect(ResetPassword::succeeded($status))->toBeTrue();

    $this->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.session_ended')]);
    $this->assertGuest();

    expect($user->fresh()?->getRememberToken())->not->toBe($rememberToken)->not->toBeEmpty();
});

it('troca de senha: quem trocou continua logado, a outra sessão cai', function (): void {
    $user = otherSessionsUser();
    $before = signInAndOpenPanel($user);
    $rememberToken = $user->fresh()?->getRememberToken();

    // A própria pessoa troca a senha nesta sessão (como o perfil faz).
    /** @var User $current */
    $current = Auth::user();
    $current->forceFill(['password' => 'senha-nova-123'])->save();

    $this->get('/dashboard')->assertOk()->assertSee('painel');
    $this->assertAuthenticatedAs($user);
    expect($current->fresh()?->getRememberToken())->not->toBe($rememberToken);

    // A sessão de outro aparelho ainda guarda a impressão de antes da troca.
    session()->put(SessionFingerprint::SESSION_KEY, $before);

    $this->getJson('/dashboard')
        ->assertUnauthorized()
        ->assertExactJson(['message' => __('auth.session_ended')]);
    $this->assertGuest();
});

it('ligar o segundo fator encerra as outras sessões e mantém a de quem ligou', function (): void {
    Mail::fake();
    $user = otherSessionsUser(['transaction_password' => 'transacao-123', 'transaction_password_set_at' => now()]);
    $before = signInAndOpenPanel($user);
    $rememberToken = $user->fresh()?->getRememberToken();

    /** @var User $current */
    $current = Auth::user();
    $sensitive = app(SensitiveActionService::class);
    $sensitive->sendCode($current, 'transacao-123');

    $code = null;
    Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    app(TwoFactorLogin::class)->enable($current, $sensitive->confirmCode($current, (string) $code)['token']);

    expect($current->fresh()?->two_factor_enabled_at)->not->toBeNull()
        ->and($current->fresh()?->getRememberToken())->not->toBe($rememberToken);

    $this->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($user);

    session()->put(SessionFingerprint::SESSION_KEY, $before);

    $this->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.session_ended')]);
    $this->assertGuest();
});

it('mudança que não toca credencial (nome, idioma) não derruba ninguém', function (): void {
    $user = otherSessionsUser();
    $before = signInAndOpenPanel($user);
    $rememberToken = $user->fresh()?->getRememberToken();

    Auth::forgetGuards();
    $user->fresh()?->forceFill(['name' => 'Outro Nome', 'locale' => 'es'])->save();

    $this->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($user);
    expect(session(SessionFingerprint::SESSION_KEY))->toBe($before)
        ->and($user->fresh()?->getRememberToken())->toBe($rememberToken);
});
