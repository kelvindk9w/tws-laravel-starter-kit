<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\AttemptLogin;
use App\Core\Auth\Actions\Logout;
use App\Core\Auth\Contracts\Responses\LoginResponse;
use App\Core\Auth\Contracts\Responses\LogoutResponse;
use App\Core\Auth\Contracts\Responses\TwoFactorRequiredResponse;
use App\Core\Auth\Enums\LoginOutcome;
use App\Core\Auth\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessão web (login/logout) — só HTTP.
 *
 * A regra (bloqueio por tentativas, deny-by-default, anti-enumeração,
 * regeneração da sessão, início do segundo fator) mora na Action
 * AttemptLogin; o encerramento, na Logout. A resposta de cada resultado vem
 * de um contrato (LoginResponse, TwoFactorRequiredResponse, LogoutResponse),
 * trocável por outro front sem tocar na regra.
 */
final class AuthenticatedSessionController
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request, AttemptLogin $attempt): Response
    {
        $outcome = $attempt->handle(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        return match ($outcome) {
            LoginOutcome::Authenticated => app(LoginResponse::class)->toResponse($request),
            LoginOutcome::TwoFactorRequired => app(TwoFactorRequiredResponse::class)->toResponse($request),
        };
    }

    public function destroy(Request $request, Logout $logout): Response
    {
        $logout->handle($request);

        return app(LogoutResponse::class)->toResponse($request);
    }
}
