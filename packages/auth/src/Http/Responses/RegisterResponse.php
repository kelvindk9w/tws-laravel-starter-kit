<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\Responses\RegisterResponse as RegisterResponseContract;
use Twstec\Kit\Auth\Support\EmailVerification;

/**
 * Padrão: com a verificação de e-mail ligada, a tela de aviso (não o
 * painel); sem ela, o dashboard.
 */
final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse(Request $request, AuthUser $user): Response
    {
        if (EmailVerification::required()) {
            return redirect()
                ->route('verification.notice')
                ->with('status', __('auth.email_verification.registered'));
        }

        return redirect()
            ->route('dashboard')
            ->with('status', __('auth.registered'));
    }
}
