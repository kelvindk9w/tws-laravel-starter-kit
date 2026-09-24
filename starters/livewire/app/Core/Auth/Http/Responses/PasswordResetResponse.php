<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\PasswordResetResponse as PasswordResetResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padrão: ao login (a pessoa entra de novo com a senha nova), com o aviso.
 */
final class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse(Request $request, string $status): Response
    {
        return redirect()->route('login')->with('status', __($status));
    }
}
