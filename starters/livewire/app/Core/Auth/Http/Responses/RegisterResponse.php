<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\RegisterResponse as RegisterResponseContract;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padrão: com a verificação de e-mail ligada, a tela de aviso (não o
 * painel); sem ela, o dashboard.
 */
final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse(Request $request, User $user): Response
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
