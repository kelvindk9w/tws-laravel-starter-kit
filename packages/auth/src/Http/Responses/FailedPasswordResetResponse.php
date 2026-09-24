<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

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
