<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\LogoutResponse as LogoutResponseContract;

/**
 * Padrão: volta ao login com o aviso de saída.
 */
final class LogoutResponse implements LogoutResponseContract
{
    public function toResponse(Request $request): Response
    {
        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }
}
