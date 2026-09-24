<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\ResendEmailVerification;
use App\Core\Auth\Actions\VerifyEmail;
use App\Core\Auth\Contracts\Responses\EmailVerificationResponse;
use App\Core\Auth\Contracts\Responses\VerifyEmailResponse;
use App\Core\Auth\Enums\EmailVerificationOutcome;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use App\Core\Auth\Support\EmailVerificationResult;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação de e-mail do cadastro: tela de aviso, reenvio e o link do e-mail.
 *
 * As três rotas exigem sessão (`auth`) e ficam FORA do `verified` — são a
 * saída de quem está barrado. Com a exigência desligada, ou com o e-mail já
 * confirmado, aviso e reenvio devolvem ao painel.
 *
 * A regra mora nas Actions VerifyEmail (assinatura relativa, expiração, conta
 * certa, hash do e-mail) e ResendEmailVerification (intervalo mínimo); a
 * resposta, nos contratos VerifyEmailResponse e EmailVerificationResponse.
 * Link inválido, adulterado ou vencido não vira página de erro crua: volta ao
 * aviso com a explicação e o botão de reenviar ao lado. Sem sessão, o `auth`
 * leva ao login e o pós-login devolve ao link (pelo SafeRedirect).
 */
final class EmailVerificationController
{
    public function notice(Request $request): View|Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! EmailVerification::pendingFor($user)) {
            return app(EmailVerificationResponse::class)
                ->toResponse($request, new EmailVerificationResult(EmailVerificationOutcome::NotPending));
        }

        return view('auth.verify-email', ['email' => $user->email]);
    }

    public function resend(Request $request, ResendEmailVerification $resend): Response
    {
        /** @var User $user */
        $user = $request->user();

        return app(EmailVerificationResponse::class)->toResponse($request, $resend->handle($user));
    }

    public function verify(Request $request, VerifyEmail $verify, string $uuid, string $hash): Response
    {
        /** @var User $user */
        $user = $request->user();

        $result = $verify->handle($request, $user, $uuid, $hash);

        if ($result->outcome === EmailVerificationOutcome::Verified) {
            return app(VerifyEmailResponse::class)->toResponse($request);
        }

        return app(EmailVerificationResponse::class)->toResponse($request, $result);
    }
}
