<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padrão: volta ao formulário com o e-mail preenchido e o motivo no campo.
 */
final class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public function toResponse(Request $request, string $status): Response
    {
        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
