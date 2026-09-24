<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\ResetPassword;
use Twstec\Kit\Auth\Contracts\Responses\FailedPasswordResetResponse;
use Twstec\Kit\Auth\Contracts\Responses\PasswordResetResponse;
use Twstec\Kit\Auth\Http\Requests\ResetPasswordRequest;

/**
 * Redefinição de senha com o token recebido por e-mail — só HTTP.
 *
 * A regra (broker nativo, senha nova, remember_token renovado, evento
 * PasswordReset) mora na Action ResetPassword; a resposta vem dos contratos
 * PasswordResetResponse / FailedPasswordResetResponse. A tela é do front (no
 * starter Livewire, a view `auth.reset-password`). O `throttle:sensitive` vem
 * com o controller (HasMiddleware).
 */
final class NewPasswordController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['store'])];
    }

    public function store(ResetPasswordRequest $request, ResetPassword $reset): Response
    {
        /** @var array{token: string, email: string, password: string} $validated */
        $validated = $request->validated();

        $status = $reset->handle($validated);

        return ResetPassword::succeeded($status)
            ? app(PasswordResetResponse::class)->toResponse($request, $status)
            : app(FailedPasswordResetResponse::class)->toResponse($request, $status);
    }
}
