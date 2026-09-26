<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\Inertia\EmailVerificationResponse;
use App\Http\Responses\Inertia\FailedPasswordResetResponse;
use App\Http\Responses\Inertia\LoginResponse;
use App\Http\Responses\Inertia\LogoutResponse;
use App\Http\Responses\Inertia\PasswordResetLinkSentResponse;
use App\Http\Responses\Inertia\PasswordResetResponse;
use App\Http\Responses\Inertia\RegisterResponse;
use App\Http\Responses\Inertia\TwoFactorChallengeResponse;
use App\Http\Responses\Inertia\TwoFactorLoginResponse;
use App\Http\Responses\Inertia\TwoFactorRequiredResponse;
use App\Http\Responses\Inertia\VerifyEmailResponse;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Support\ServiceProvider;
use Twstec\Kit\Auth\Contracts\Responses;
use Twstec\Kit\Foundation\Kit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * As respostas HTTP dos fluxos de autenticação, na versão Inertia.
     *
     * A REGRA de cada fluxo (bloqueio por tentativas, sessão regenerada,
     * segundo fator, e-mails, eventos) é do pacote twstec/kit-auth, e roda
     * antes da resposta — trocar a resposta não mexe nela. O pacote registra
     * as respostas padrão com `bindIf`; o registro daqui prevalece.
     *
     * @var array<class-string, class-string>
     */
    public const AUTH_RESPONSES = [
        Responses\LoginResponse::class => LoginResponse::class,
        Responses\TwoFactorRequiredResponse::class => TwoFactorRequiredResponse::class,
        Responses\TwoFactorLoginResponse::class => TwoFactorLoginResponse::class,
        Responses\TwoFactorChallengeResponse::class => TwoFactorChallengeResponse::class,
        Responses\LogoutResponse::class => LogoutResponse::class,
        Responses\RegisterResponse::class => RegisterResponse::class,
        Responses\PasswordResetLinkSentResponse::class => PasswordResetLinkSentResponse::class,
        Responses\PasswordResetResponse::class => PasswordResetResponse::class,
        Responses\FailedPasswordResetResponse::class => FailedPasswordResetResponse::class,
        Responses\VerifyEmailResponse::class => VerifyEmailResponse::class,
        Responses\EmailVerificationResponse::class => EmailVerificationResponse::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach (self::AUTH_RESPONSES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        // O painel /admin é OPCIONAL (twstec/kit-admin, que traz o Filament):
        // o PanelProvider do aplicativo só é registrado com o pacote
        // instalado. É o MESMO painel do starter Livewire.
        if (Kit::has('admin')) {
            $this->app->register(AdminPanelProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As guardas de produção, os limitadores (`api`, `sensitive`) e a
        // pilha de segurança são do pacote twstec/kit-foundation; as
        // proteções da sessão web (status da conta, `verified`,
        // `sensitive.token`), do twstec/kit-auth.
    }
}
