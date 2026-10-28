<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Twstec\Kit\Auth\Actions\ResetPassword;
use Twstec\Kit\Auth\Exceptions\TwoFactorLockedException;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// FORÇA BRUTA NO LOGIN, DE MUITAS ORIGENS — numa aplicação limpa.
//
// O limite do login era por e-mail + IP exato, e o do código do segundo fator
// por IP exato: um host IPv6 (que recebe um /64 inteiro) ou uma botnet
// ganhava orçamento novo a cada endereço. Aqui se prova que (1) trocar de
// endereço dentro do mesmo prefixo IPv6 não dá orçamento novo e (2) a conta
// tem um teto próprio, somando todas as origens.
// =============================================================================

beforeEach(function (): void {
    // O limite de rota (throttle:sensitive) fica fora do caminho: o que está
    // sob teste são os limites da própria regra.
    config(['security.rate_limit.sensitive' => 1000]);
});

function loginFrom(string $ip, string $email, string $password): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/login', ['email' => $email, 'password' => $password]);
}

/**
 * O começo da mensagem de bloqueio, até o primeiro marcador (`:seconds`).
 */
function throttlePrefix(): string
{
    return rtrim((string) strstr(__('auth.throttle').':', ':', true));
}

it('trocar de endereço IPv6 dentro do mesmo /64 não reinicia o limite do login', function (): void {
    config(['auth.login.max_attempts' => 3, 'auth.login.max_attempts_per_account' => 1000]);
    $user = User::fixture();

    foreach (['2001:db8:7:1::1', '2001:db8:7:1::2', '2001:db8:7:1:abcd::3'] as $ip) {
        loginFrom($ip, $user->email, 'errada')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // Quarto endereço do mesmo /64, com a senha CERTA: ainda bloqueado.
    loginFrom('2001:db8:7:1:ffff::4', $user->email, 'senha-correta')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith(throttlePrefix())
        ->and(session('errors')->first('email'))->not->toBe(__('auth.failed'));
    $this->assertGuest();

    // Outro /64 é outro cliente.
    loginFrom('2001:db8:7:2::1', $user->email, 'senha-correta')->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('a conta tem teto próprio: muitos IPs diferentes não testam senhas à vontade', function (): void {
    config([
        'auth.login.max_attempts' => 3,
        'auth.login.max_attempts_per_account' => 5,
        'auth.login.lockout_minutes' => 15,
    ]);
    $alvo = User::fixture();
    $outra = User::fixture();

    // Cinco origens diferentes, uma tentativa cada — nenhuma chega ao limite
    // por cliente (3), mas a conta soma cinco.
    foreach (range(1, 5) as $n) {
        loginFrom('198.51.100.'.$n, $alvo->email, 'errada-'.$n)->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // Origem nova, senha certa: a conta está bloqueada, com a mensagem de
    // bloqueio (a mesma do limite por cliente).
    loginFrom('203.0.113.200', $alvo->email, 'senha-correta')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith(throttlePrefix())
        ->and(session('errors')->first('email'))->not->toBe(__('auth.failed'));
    $this->assertGuest();

    // As outras contas não pagam pelo ataque a esta.
    loginFrom('203.0.113.200', $outra->email, 'senha-correta')->assertRedirect();
    $this->assertAuthenticatedAs($outra);
    auth()->logout();

    // O bloqueio é temporário.
    $this->travel(16)->minutes();

    loginFrom('203.0.113.201', $alvo->email, 'senha-correta')->assertRedirect();
    $this->assertAuthenticatedAs($alvo);
});

it('conta inexistente bloqueia igual (o teto por conta não enumera e-mails)', function (): void {
    config(['auth.login.max_attempts' => 100, 'auth.login.max_attempts_per_account' => 2]);

    foreach (range(1, 2) as $n) {
        loginFrom('198.51.100.'.$n, 'ninguem@example.com', 'errada')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    loginFrom('198.51.100.9', 'ninguem@example.com', 'errada')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->not->toBe(__('auth.failed'));
});

it('a redefinição de senha por e-mail tira a conta do bloqueio por conta', function (): void {
    config(['auth.login.max_attempts' => 100, 'auth.login.max_attempts_per_account' => 2]);
    $user = User::fixture();

    foreach (range(1, 2) as $n) {
        loginFrom('198.51.100.'.$n, $user->email, 'errada');
    }

    loginFrom('198.51.100.3', $user->email, 'senha-correta')->assertSessionHasErrors('email');
    $this->assertGuest();

    $status = app(ResetPassword::class)->handle([
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'senha-nova-123',
    ]);

    expect(ResetPassword::succeeded($status))->toBeTrue();

    loginFrom('198.51.100.3', $user->email, 'senha-nova-123')->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('o limite por origem do código do segundo fator conta o IPv6 pelo prefixo', function (): void {
    Mail::fake();
    config([
        'auth.two_factor.max_attempts_per_account' => 1000,
        'auth.two_factor.max_attempts_per_ip' => 2,
    ]);
    $service = app(TwoFactorLogin::class);

    $erra = function (User $user, string $ip) use ($service): void {
        $service->sendCode($user);

        $code = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        try {
            $service->verify($user, $wrong, $ip);
        } catch (TwoFactorLockedException) {
        }
    };

    $erra(User::fixture(['two_factor_enabled_at' => now()]), '2001:db8:9:1::1');
    $erra(User::fixture(['two_factor_enabled_at' => now()]), '2001:db8:9:1:aaaa::2');

    // Terceira conta, terceiro endereço do mesmo /64: a origem está bloqueada.
    $terceira = User::fixture(['two_factor_enabled_at' => now()]);

    expect($service->lockedSeconds($terceira, '2001:db8:9:1:ffff::3'))->toBeGreaterThan(0)
        ->and($service->lockedSeconds($terceira, '2001:db8:9:2::1'))->toBe(0);
});
