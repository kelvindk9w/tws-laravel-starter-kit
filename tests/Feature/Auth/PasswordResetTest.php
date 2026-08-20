<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Recuperação de senha por e-mail (broker nativo do Laravel: token com hash
// + expiração). Anti-enumeração: mesma resposta para e-mail inexistente.

it('exibe o formulário de recuperação de senha', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee(__('auth.ui.forgot_title'));
});

it('envia o link de redefinição para e-mail cadastrado', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('responde igual para e-mail NÃO cadastrado (anti-enumeração — item 11)', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'ninguem@example.com'])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();
});

it('redefine a senha com token válido e invalida o remember_token', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'SenhaAntiga123']);

    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'SenhaNova456',
        'password_confirmation' => 'SenhaNova456',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', __('passwords.reset'));

    $user->refresh();

    expect(Hash::check('SenhaNova456', $user->password))->toBeTrue()
        ->and($user->password)->toStartWith('$argon2id$');

    // O token não pode ser reutilizado.
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'OutraSenha789',
        'password_confirmation' => 'OutraSenha789',
    ])->assertSessionHasErrors('email');
});

it('rejeita token inválido', function () {
    $user = User::factory()->create(['password' => 'SenhaAntiga123']);

    $this->post('/reset-password', [
        'token' => 'token-invalido',
        'email' => $user->email,
        'password' => 'SenhaNova456',
        'password_confirmation' => 'SenhaNova456',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('SenhaAntiga123', $user->fresh()->password))->toBeTrue();
});

it('rejeita senha nova fraca na redefinição', function () {
    $user = User::factory()->create();

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'fraca',
        'password_confirmation' => 'fraca',
    ])->assertSessionHasErrors('password');
});
