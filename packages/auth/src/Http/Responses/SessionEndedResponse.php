<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\SessionEndedResponse as SessionEndedResponseContract;

/**
 * Padrão: 401 em JSON; no navegador, volta ao login com o aviso de sessão
 * encerrada no campo de e-mail.
 */
final class SessionEndedResponse implements SessionEndedResponseContract
{
    public function toResponse(Request $request): Response
    {
        $message = __('auth.session_ended');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
