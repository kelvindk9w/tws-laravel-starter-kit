<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\LoginResponse as LoginResponseContract;
use App\Core\Auth\Support\PostLoginRedirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
