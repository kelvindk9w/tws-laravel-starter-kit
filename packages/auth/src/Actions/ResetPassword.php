<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Redefine a senha com o token recebido por e-mail.
 *
 * Após a troca, TODAS as sessões antigas caem: remember_token renovado, e o
 * evento PasswordReset é disparado. Devolve o status do broker
 * (Password::PasswordReset em caso de sucesso), que escolhe o contrato de
 * resposta: PasswordResetResponse ou FailedPasswordResetResponse.
 */
final class ResetPassword
{
    /**
     * @param  array{token: string, email: string, password: string}  $input  Dados já validados.
     */
    public function handle(array $input): string
    {
        /** @var string */
        return Password::reset(
            $input,
            function (AuthUser $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );
    }

    public static function succeeded(string $status): bool
    {
        return $status === Password::PasswordReset;
    }
}
