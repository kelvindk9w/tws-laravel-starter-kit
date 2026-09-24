<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\ResetPassword;
use App\Core\Auth\Contracts\Responses\FailedPasswordResetResponse;
use App\Core\Auth\Contracts\Responses\PasswordResetResponse;
use App\Core\Auth\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redefinição de senha com o token recebido por e-mail — só HTTP.
 *
 * A regra (broker nativo, senha nova, remember_token renovado, evento
 * PasswordReset) mora na Action ResetPassword; a resposta vem dos contratos
 * PasswordResetResponse / FailedPasswordResetResponse.
 */
final class NewPasswordController
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
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
