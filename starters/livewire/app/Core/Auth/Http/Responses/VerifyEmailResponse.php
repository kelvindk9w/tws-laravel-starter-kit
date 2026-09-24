<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\VerifyEmailResponse as VerifyEmailResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Foundation\Http\SafeRedirect;

/**
 * Padrão: volta ao destino original guardado antes do login (pelo
 * SafeRedirect — só para dentro da aplicação), ou ao dashboard.
 */
final class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse(Request $request): Response
    {
        $intended = $request->session()->pull('url.intended');

        return redirect()
            ->to(SafeRedirect::url(is_string($intended) ? $intended : null, route('dashboard')))
            ->with('status', __('auth.email_verification.verified'));
    }
}
