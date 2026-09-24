<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\ResendEmailVerification;
use Twstec\Kit\Auth\Actions\VerifyEmail;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\Responses\EmailVerificationResponse;
use Twstec\Kit\Auth\Contracts\Responses\VerifyEmailResponse;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;

/**
 * Verificação de e-mail do cadastro: o reenvio e o link do e-mail.
 *
 * As rotas exigem sessão (`auth`) e ficam FORA do `verified` — são a saída de
 * quem está barrado. A tela de aviso é do front (no starter Livewire, a view
 * `auth.verify-email`). O `throttle:sensitive` vem com o controller
 * (HasMiddleware).
 *
 * A regra mora nas Actions VerifyEmail (assinatura relativa, expiração, conta
 * certa, hash do e-mail) e ResendEmailVerification (intervalo mínimo); a
 * resposta, nos contratos VerifyEmailResponse e EmailVerificationResponse.
 * Link inválido, adulterado ou vencido não vira página de erro crua: volta ao
 * aviso com a explicação e o botão de reenviar ao lado. Sem sessão, o `auth`
 * leva ao login e o pós-login devolve ao link (pelo SafeRedirect).
 */
final class EmailVerificationController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['resend', 'verify'])];
    }

    public function resend(Request $request, ResendEmailVerification $resend): Response
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return app(EmailVerificationResponse::class)->toResponse($request, $resend->handle($user));
    }

    public function verify(Request $request, VerifyEmail $verify, string $uuid, string $hash): Response
    {
        /** @var AuthUser $user */
        $user = $request->user();

        $result = $verify->handle($request, $user, $uuid, $hash);

        if ($result->outcome === EmailVerificationOutcome::Verified) {
            return app(VerifyEmailResponse::class)->toResponse($request);
        }

        return app(EmailVerificationResponse::class)->toResponse($request, $result);
    }
}
