<?php

declare(strict_types=1);

use Twstec\Kit\Auth\Support\UserModel;
use Twstec\Kit\Auth\Tests\Fixtures\User;

it('trabalha com o model de usuário que a aplicação configurou', function (): void {
    expect(UserModel::name())->toBe(User::class)
        ->and(UserModel::make())->toBeInstanceOf(User::class)
        ->and(UserModel::query()->getModel())->toBeInstanceOf(User::class);
});

it('recusa alto um model que não implementa o contrato do pacote', function (): void {
    config(['auth.providers.users.model' => Illuminate\Foundation\Auth\User::class]);

    UserModel::name();
})->throws(LogicException::class, 'precisa ser um model Eloquent que implementa');

it('recusa alto um model que não existe', function (): void {
    config(['auth.providers.users.model' => 'App\\Models\\NaoExiste']);

    UserModel::name();
})->throws(LogicException::class, 'não aponta para uma classe existente');

it('as traits do pacote dão ao model o que as regras usam', function (): void {
    $user = User::fixture();

    expect($user->isActive())->toBeTrue()
        ->and($user->hasTransactionPassword())->toBeFalse()
        ->and($user->isReservedAccount())->toBeFalse()
        ->and($user->getCasts())->toMatchArray([
            'transaction_password' => 'hashed',
            'transaction_password_set_at' => 'datetime',
            'two_factor_enabled_at' => 'datetime',
        ]);

    // Instância nova já nasce ativa (o default da coluna).
    expect((new User)->isActive())->toBeTrue();
});
