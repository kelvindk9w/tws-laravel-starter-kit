<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * A REGRA da verificação de e-mail no cadastro, em um lugar só.
 *
 * Quem decide se uma conta pode operar sem ter confirmado o e-mail é esta
 * classe — o middleware do painel (EnsureEmailIsVerified), a API
 * (ResolveTenant), as telas de aviso e o envio no cadastro perguntam aqui, e
 * a flag AUTH_EMAIL_VERIFICATION_REQUIRED vale para todos ao mesmo tempo.
 *
 * O LINK DO E-MAIL NÃO NASCE DO HOST DA REQUISIÇÃO. `route()` monta a URL com
 * o `Host` que chegou (dado do cliente) quando é chamado dentro de uma
 * requisição — e o e-mail pode ser montado assim numa fila síncrona. Aqui a
 * assinatura é calculada sobre o CAMINHO (relativa) e a origem vem sempre de
 * APP_URL. Mesmo que um dia o TrustHosts seja afrouxado, o link que chega à
 * caixa de entrada aponta para a aplicação, nunca para o host de quem pediu.
 * A validação é a par: `hasValidRelativeSignature`, que também ignora o host.
 */
final class EmailVerification
{
    /**
     * A exigência está ligada nesta instalação? (Padrão: sim.)
     */
    public static function required(): bool
    {
        return (bool) config('auth.email_verification.required', true);
    }

    /**
     * Esta conta está barrada por falta de verificação AGORA?
     */
    public static function pendingFor(?User $user): bool
    {
        return $user !== null && self::required() && ! $user->hasVerifiedEmail();
    }

    public static function linkTtlMinutes(): int
    {
        return max(1, (int) config('auth.email_verification.link_ttl_minutes', 60));
    }

    public static function resendCooldownSeconds(): int
    {
        return max(1, (int) config('auth.email_verification.resend_cooldown_seconds', 60));
    }

    /**
     * Link assinado e com expiração, ancorado em APP_URL.
     *
     * Identifica a conta pelo `uuid` (o `id` interno nunca sai da aplicação) e
     * carrega o hash do e-mail: se o e-mail da conta mudar depois do envio, o
     * link antigo deixa de valer.
     */
    public static function verificationUrl(User $user): string
    {
        $path = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::linkTtlMinutes()),
            ['uuid' => (string) $user->uuid, 'hash' => self::emailHash($user)],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * O link recebido é desta conta e não foi adulterado nem expirou?
     */
    public static function linkIsValidFor(Request $request, User $user, string $uuid, string $hash): bool
    {
        return $request->hasValidRelativeSignature()
            && hash_equals((string) $user->uuid, $uuid)
            && hash_equals(self::emailHash($user), $hash);
    }

    /**
     * Envia o e-mail se a conta ainda não passou pelo cooldown. Devolve os
     * segundos que faltam quando está em espera (0 = enviado agora).
     */
    public static function sendIfAllowed(User $user): int
    {
        $key = self::cooldownKey($user);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return max(1, RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::resendCooldownSeconds());

        $user->sendEmailVerificationNotification();

        return 0;
    }

    private static function emailHash(User $user): string
    {
        return sha1($user->getEmailForVerification());
    }

    private static function cooldownKey(User $user): string
    {
        return 'email-verification|'.$user->getAuthIdentifier();
    }
}
