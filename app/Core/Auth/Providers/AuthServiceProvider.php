<?php

declare(strict_types=1);

namespace App\Core\Auth\Providers;

use App\Core\Auth\Contracts\Responses;
use App\Core\Auth\Http\Responses as Defaults;
use Illuminate\Support\ServiceProvider;

/**
 * Registra as respostas HTTP padrão dos fluxos de autenticação.
 *
 * Cada contrato de App\Core\Auth\Contracts\Responses ganha aqui a
 * implementação que reproduz as telas Blade do kit. O registro é `bindIf`:
 * um front que já registrou a própria resposta (ou que registra depois, num
 * provider do app) prevalece sobre o padrão — a regra de negócio (as Actions)
 * não muda.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Contrato => implementação padrão.
     *
     * @var array<class-string, class-string>
     */
    public const RESPONSES = [
        Responses\LoginResponse::class => Defaults\LoginResponse::class,
        Responses\TwoFactorRequiredResponse::class => Defaults\TwoFactorRequiredResponse::class,
        Responses\TwoFactorLoginResponse::class => Defaults\TwoFactorLoginResponse::class,
        Responses\TwoFactorChallengeResponse::class => Defaults\TwoFactorChallengeResponse::class,
        Responses\LogoutResponse::class => Defaults\LogoutResponse::class,
        Responses\RegisterResponse::class => Defaults\RegisterResponse::class,
        Responses\PasswordResetLinkSentResponse::class => Defaults\PasswordResetLinkSentResponse::class,
        Responses\PasswordResetResponse::class => Defaults\PasswordResetResponse::class,
        Responses\FailedPasswordResetResponse::class => Defaults\FailedPasswordResetResponse::class,
        Responses\VerifyEmailResponse::class => Defaults\VerifyEmailResponse::class,
        Responses\EmailVerificationResponse::class => Defaults\EmailVerificationResponse::class,
    ];

    public function register(): void
    {
        foreach (self::RESPONSES as $contract => $default) {
            $this->app->bindIf($contract, $default);
        }
    }
}
