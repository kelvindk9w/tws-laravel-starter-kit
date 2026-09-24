<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\LoginResponse as LoginResponseContract;
use Twstec\Kit\Auth\Support\PostLoginRedirect;

/**
 * Padrão: volta ao destino original pelo SafeRedirect (PostLoginRedirect),
 * ou ao dashboard.
 */
final class LoginResponse implements LoginResponseContract
{
    public function toResponse(Request $request): Response
    {
        return PostLoginRedirect::to($request);
    }
}
