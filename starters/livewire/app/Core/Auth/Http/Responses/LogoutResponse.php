<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
