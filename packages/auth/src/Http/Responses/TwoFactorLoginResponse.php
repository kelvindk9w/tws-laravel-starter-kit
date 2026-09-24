<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Twstec\Kit\Auth\Support\PostLoginRedirect;

/**
 * Padrão: o mesmo destino do login direto (PostLoginRedirect, pelo SafeRedirect).
 */
final class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse(Request $request): Response
    {
        return PostLoginRedirect::to($request);
    }
}
