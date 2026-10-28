<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// CONTA INATIVA NÃO SE DENUNCIA PELO TEMPO DE RESPOSTA.
//
// Cada conferência de senha custa um hash lento (e o guard a embrulha num
// tempo mínimo). Se a conta inativa com senha ERRADA conferisse a senha duas
// vezes e a conta ativa uma, o tempo de resposta separaria as duas — e
// revelaria quais e-mails são de contas bloqueadas, mesmo com a mensagem
// igual. A senha é conferida UMA vez em qualquer caso.
// =============================================================================

beforeEach(function (): void {
    config(['security.rate_limit.sensitive' => 1000]);
});

/**
 * Conta as conferências de senha do login (o provider de usuários resolve o
 * hasher de novo depois do Auth::forgetGuards()).
 */
function countPasswordChecks(): object
{
    $counter = new class
    {
        public int $checks = 0;
    };

    $hasher = Hash::driver();

    Hash::partialMock()
        ->shouldReceive('check')
        ->andReturnUsing(function (...$arguments) use ($counter, $hasher): bool {
            $counter->checks++;

            return $hasher->check(...$arguments);
        });

    Auth::forgetGuards();

    return $counter;
}

it('confere a senha uma vez só, com ou sem conta ativa e com senha certa ou errada', function (UserStatus $status, string $password, string $message): void {
    $user = User::fixture(['status' => $status]);
    $counter = countPasswordChecks();

    $this->post('/login', ['email' => $user->email, 'password' => $password])
        ->assertSessionHasErrors(['email' => __($message)]);

    expect($counter->checks)->toBe(1);
    $this->assertGuest();
})->with([
    'inativa, senha errada' => [UserStatus::Blocked, 'senha-errada', 'auth.failed'],
    'inativa, senha certa' => [UserStatus::Blocked, 'senha-correta', 'auth.account_inactive'],
    'ativa, senha errada' => [UserStatus::Active, 'senha-errada', 'auth.failed'],
]);
