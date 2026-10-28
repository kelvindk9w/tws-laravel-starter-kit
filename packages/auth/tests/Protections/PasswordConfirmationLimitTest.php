<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Services\PasswordConfirmation;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Services\TransactionPasswordService;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// SENHA CONFERIDA COM A SESSÃO JÁ ABERTA TEM LIMITE NA PRÓPRIA REGRA.
//
// Os serviços são chamados direto, como uma ação Livewire os chama: sem rota,
// sem `throttle:sensitive`. Quem está com a sessão de outra pessoa não pode
// testar a senha de transação (nem a de login) sem teto.
// =============================================================================

beforeEach(function (): void {
    Mail::fake();
    config(['auth.password_confirmation.max_attempts' => 3, 'auth.password_confirmation.lockout_minutes' => 15]);
});

function withTransactionPassword(): User
{
    return User::fixture(['transaction_password' => 'transacao-123', 'transaction_password_set_at' => now()]);
}

/**
 * Executa e devolve a primeira mensagem do campo (ou null se não recusou).
 */
function refusal(callable $call, string $field): ?string
{
    try {
        $call();
    } catch (ValidationException $exception) {
        return $exception->errors()[$field][0] ?? null;
    }

    return null;
}

function lockedMessage(): string
{
    return rtrim((string) strstr(__('auth.password_confirmation.locked').':', ':', true));
}

it('pedir o código da ação sensível: senha de transação errada demais bloqueia, até com a certa', function (): void {
    $user = withTransactionPassword();
    $service = app(SensitiveActionService::class);

    foreach (range(1, 3) as $n) {
        expect(refusal(fn () => $service->sendCode($user, 'errada-'.$n), 'transaction_password'))
            ->toBe(__('auth.transaction_password.invalid'));
    }

    expect(refusal(fn () => $service->sendCode($user, 'transacao-123'), 'transaction_password'))
        ->toStartWith(lockedMessage());
    Mail::assertNothingQueued();

    // A janela passa: a senha certa volta a valer.
    $this->travel(16)->minutes();

    $service->sendCode($user, 'transacao-123');
    Mail::assertQueued(VerificationCodeMail::class);
});

it('o pedido de código e a troca da senha de transação somam no mesmo contador', function (): void {
    $user = withTransactionPassword();

    refusal(fn () => app(SensitiveActionService::class)->sendCode($user, 'errada-1'), 'transaction_password');
    refusal(fn () => app(SensitiveActionService::class)->sendCode($user, 'errada-2'), 'transaction_password');

    expect(refusal(fn () => app(TransactionPasswordService::class)->update($user, 'nova-transacao-1', 'errada-3'), 'current_transaction_password'))
        ->toBe(__('auth.transaction_password.current_invalid'));

    expect(refusal(fn () => app(TransactionPasswordService::class)->update($user, 'nova-transacao-1', 'transacao-123'), 'current_transaction_password'))
        ->toStartWith(lockedMessage());
    expect(Hash::check('transacao-123', (string) $user->fresh()?->transaction_password))->toBeTrue();
});

it('a recusa "igual à senha de login" não vira adivinhador da senha de login', function (): void {
    // Sessão de outra pessoa, sem senha de transação ainda: cada palpite vira
    // a senha de transação, e o próximo usa o anterior como "atual". Sem
    // limite, o palpite certo apareceria como "igual à senha de login".
    $user = User::fixture();
    $service = app(TransactionPasswordService::class);

    $service->update($user, 'palpite-1');
    $service->update($user->fresh() ?? $user, 'palpite-2', 'palpite-1');
    $service->update($user->fresh() ?? $user, 'palpite-3', 'palpite-2');

    $message = refusal(fn () => $service->update($user->fresh() ?? $user, 'senha-correta', 'palpite-3'), 'transaction_password');

    expect($message)->not->toBe(__('auth.transaction_password.same_as_login'))
        ->and($message)->toStartWith(lockedMessage());
});

it('senha de login atual (troca no perfil): erro conta, esgotou bloqueia, acerto zera', function (): void {
    $user = User::fixture();
    $passwords = app(PasswordConfirmation::class);

    expect($passwords->confirmLoginPassword($user, 'errada', 'current_password'))->toBeFalse()
        ->and($passwords->confirmLoginPassword($user, 'senha-correta', 'current_password'))->toBeTrue();

    // O acerto zerou: mais três erros até bloquear.
    foreach (range(1, 3) as $n) {
        expect($passwords->confirmLoginPassword($user, 'errada-'.$n, 'current_password'))->toBeFalse();
    }

    expect(refusal(fn () => $passwords->confirmLoginPassword($user, 'senha-correta', 'current_password'), 'current_password'))
        ->toStartWith(lockedMessage());

    // Cada conta tem o seu contador.
    expect($passwords->confirmLoginPassword(User::fixture(), 'senha-correta', 'current_password'))->toBeTrue();
});
