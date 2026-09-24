<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use App\Core\Auth\Support\PostLoginRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
