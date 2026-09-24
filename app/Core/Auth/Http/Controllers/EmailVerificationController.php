<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use App\Core\Http\SafeRedirect;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verificação de e-mail do cadastro: tela de aviso, reenvio e o link do e-mail.
 *
 * As três rotas exigem sessão (`auth`) e ficam FORA do `verified` — são a
 * saída de quem está barrado. Com a exigência desligada, ou com o e-mail já
 * confirmado, aviso e reenvio devolvem ao painel.
 *
 * O LINK: assinatura relativa (o host não entra nela nem na URL do e-mail —
 * ver EmailVerification), expiração, `uuid` da conta e hash do e-mail. Ele
 * precisa ser aberto com a própria conta logada; sem sessão, o `auth` leva ao
 * login e o pós-login devolve ao link (pelo SafeRedirect). Link inválido,
 * adulterado ou vencido não vira página de erro crua: volta ao aviso com a
 * explicação e o botão de reenviar ao lado.
 */
final class EmailVerificationController
{
    public function notice(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! EmailVerification::pendingFor($user)) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', ['email' => $user->email]);
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! EmailVerification::pendingFor($user)) {
            return redirect()->route('dashboard');
        }

        $wait = EmailVerification::sendIfAllowed($user);

        if ($wait > 0) {
            return redirect()->route('verification.notice')
                ->with('verification_error', __('auth.email_verification.cooldown', ['seconds' => $wait]));
        }

        return redirect()->route('verification.notice')
            ->with('status', __('auth.email_verification.sent'));
    }

    public function verify(Request $request, string $uuid, string $hash): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! hash_equals((string) $user->uuid, $uuid)) {
            return redirect()->route('verification.notice')
                ->with('verification_error', __('auth.email_verification.wrong_account'));
        }

        if (! EmailVerification::linkIsValidFor($request, $user, $uuid, $hash)) {
            return redirect()->route('verification.notice')
                ->with('verification_error', __('auth.email_verification.invalid_link'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        $intended = $request->session()->pull('url.intended');

        return redirect()
            ->to(SafeRedirect::url(is_string($intended) ? $intended : null, route('dashboard')))
            ->with('status', __('auth.email_verification.verified'));
    }
}
