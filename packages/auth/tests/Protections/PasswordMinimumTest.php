<?php

declare(strict_types=1);

use Twstec\Kit\Auth\PasswordPolicy;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// O MÍNIMO DE FÁBRICA DA SENHA DE LOGIN É 8 — numa aplicação limpa, sem
// AUTH_PASSWORD_MIN no ambiente: senha de 7 caracteres não abre conta.
// =============================================================================

it('sem configuração, o cadastro recusa senha de 7 caracteres e aceita a de 8', function (): void {
    config(['security.rate_limit.sensitive' => 1000]);

    $this->post('/register', [
        'name' => 'Pessoa',
        'email' => 'curta@example.com',
        'password' => 'abcdefg',
        'password_confirmation' => 'abcdefg',
    ])->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'curta@example.com')->exists())->toBeFalse();

    $this->post('/register', [
        'name' => 'Pessoa',
        'email' => 'longa@example.com',
        'password' => 'abcdefgh',
        'password_confirmation' => 'abcdefgh',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'longa@example.com')->exists())->toBeTrue();
});

it('a dica do formulário anuncia o mínimo de 8', function (): void {
    app()->setLocale('pt_BR');

    expect(PasswordPolicy::hint())->toBe(__('auth.password_policy.min', ['min' => 8]));
});
