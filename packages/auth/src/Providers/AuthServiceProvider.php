<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Providers;

use Illuminate\Auth\Middleware\EnsureEmailIsVerified as FrameworkEnsureEmailIsVerified;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Twstec\Kit\Auth\Contracts\Responses;
use Twstec\Kit\Auth\Http\Middleware\EnsureAccountIsActive;
use Twstec\Kit\Auth\Http\Middleware\EnsureEmailIsVerified;
use Twstec\Kit\Auth\Http\Middleware\RequiresSensitiveActionToken;
use Twstec\Kit\Auth\Http\Responses as Defaults;
use Twstec\Kit\Foundation\Localization\PackageTranslations;

/**
 * O que o pacote de autenticação instala numa aplicação Laravel — sozinho,
 * sem a aplicação precisar lembrar de chamar nada:
 *
 * - a configuração padrão das chaves do kit em `config('auth')`;
 * - as migrations (colunas de autenticação em `users`, códigos de
 *   verificação e tokens de ação sensível), com os MESMOS nomes de arquivo
 *   que tinham no aplicativo;
 * - as traduções do domínio (`auth.*` e os assuntos dos e-mails), com o
 *   aplicativo vencendo na mesma chave;
 * - as respostas HTTP padrão dos fluxos (contratos de Contracts\Responses);
 * - as PROTEÇÕES da sessão web: o status da conta conferido a cada requisição
 *   do grupo `web` (EnsureAccountIsActive) e os aliases `verified` (e-mail
 *   confirmado, com a regra do kit) e `sensitive.token` (token de ação
 *   sensível de uso único).
 *
 * As proteções que ficam nas próprias regras continuam lá e não dependem de
 * rota nem de provider: bloqueio de login por tentativas (AttemptLogin),
 * limites do código do segundo fator por código, conta e IP (TwoFactorLogin),
 * intervalo de reenvio (EmailVerification, VerificationCodes). Os controllers
 * do pacote trazem o próprio `throttle:sensitive` (HasMiddleware).
 *
 * Opt-out das proteções web: `auth.web_protections.enabled = false`
 * (AUTH_WEB_PROTECTIONS=false) — com aviso no log a cada boot.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Contrato => implementação padrão.
     *
     * Cada contrato ganha aqui a implementação que reproduz as telas Blade do
     * starter (redirecionamentos para as rotas nomeadas `login`, `dashboard`,
     * `two-factor.challenge` e `verification.notice`). O registro é `bindIf`:
     * um front que já registrou a própria resposta (ou que registra depois,
     * num provider do app) prevalece sobre o padrão — a regra de negócio (as
     * Actions) não muda.
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

    /**
     * Middleware anexado ao FIM do grupo `web` (depois do que a aplicação
     * declarou nele — no starter, depois do SetLocale, para a mensagem de
     * recusa sair no idioma da conta).
     *
     * @var list<class-string>
     */
    public const WEB_MIDDLEWARE = [
        EnsureAccountIsActive::class,
    ];

    /**
     * Aliases do pacote. `sensitive.token` só entra se a aplicação não
     * declarou um de mesmo nome. `verified` substitui o do FRAMEWORK (que não
     * conhece a regra do kit — exigência desligável, conta protegida, ações
     * Livewire); um `verified` próprio da aplicação prevalece.
     *
     * @var array<string, class-string>
     */
    public const MIDDLEWARE_ALIASES = [
        'sensitive.token' => RequiresSensitiveActionToken::class,
        'verified' => EnsureEmailIsVerified::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/auth.php'), 'auth');

        foreach (self::RESPONSES as $contract => $default) {
            $this->app->bindIf($contract, $default);
        }

        PackageTranslations::register($this->app, $this->path('lang'));
    }

    public function boot(): void
    {
        $this->registerWebProtections();

        // As migrations rodam direto daqui, com os MESMOS nomes de arquivo
        // que tinham quando moravam no aplicativo: um banco que já as rodou
        // não vê nada pendente, e um banco novo as roda na mesma ordem.
        $this->loadMigrationsFrom($this->path('database/migrations'));
    }

    /**
     * Instala as proteções da sessão web quando o kernel HTTP é resolvido —
     * depois de a aplicação montar a pilha dela (bootstrap/app.php) — ou na
     * hora, se ele já foi resolvido.
     */
    private function registerWebProtections(): void
    {
        if (config('auth.web_protections.enabled', true) === false) {
            Log::warning('AUTH_WEB_PROTECTIONS=false: as proteções web do twstec/kit-auth estão DESLIGADAS — o status da conta NÃO é conferido a cada requisição do grupo `web` (conta bloqueada com sessão aberta continua operando) e os aliases `verified` e `sensitive.token` não são instalados pelo pacote. Só é seguro se a aplicação instalar as mesmas proteções por conta própria. Ver Twstec\Kit\Auth\Providers\AuthServiceProvider.');

            return;
        }

        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel): void {
            if (! $kernel instanceof HttpKernel) {
                throw new LogicException(sprintf(
                    'twstec/kit-auth precisa de um kernel HTTP que estenda %s para instalar as proteções da sessão web; recebeu %s.',
                    HttpKernel::class,
                    $kernel::class,
                ));
            }

            if (! array_key_exists('web', $kernel->getMiddlewareGroups())) {
                throw new LogicException('twstec/kit-auth: o grupo de middleware `web` não existe; o status da conta não teria onde ser conferido.');
            }

            foreach (self::WEB_MIDDLEWARE as $middleware) {
                $kernel->appendMiddlewareToGroup('web', $middleware);
            }

            $aliases = $kernel->getMiddlewareAliases();

            foreach (self::MIDDLEWARE_ALIASES as $alias => $middleware) {
                $current = $aliases[$alias] ?? null;

                if ($current === null || $current === FrameworkEnsureEmailIsVerified::class) {
                    $aliases[$alias] = $middleware;
                }
            }

            $kernel->setMiddlewareAliases($aliases);
        });
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).'/'.$relative;
    }
}
