<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\AttemptLogin;
use Twstec\Kit\Auth\Actions\Logout;
use Twstec\Kit\Auth\Contracts\Responses\LoginResponse;
use Twstec\Kit\Auth\Contracts\Responses\LogoutResponse;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorRequiredResponse;
use Twstec\Kit\Auth\Enums\LoginOutcome;
use Twstec\Kit\Auth\Http\Requests\LoginRequest;

/**
 * Sessão web (login/logout) — só HTTP.
 *
 * A regra (bloqueio por tentativas, deny-by-default, anti-enumeração,
 * regeneração da sessão, início do segundo fator) mora na Action
 * AttemptLogin; o encerramento, na Logout. A resposta de cada resultado vem
 * de um contrato (LoginResponse, TwoFactorRequiredResponse, LogoutResponse),
 * trocável por outro front sem tocar na regra.
 *
 * A TELA de login é do front (no starter Livewire, a view `auth.login`); este
 * controller só recebe o formulário. O `throttle:sensitive` vem com ele
 * (HasMiddleware): qualquer rota que aponte para o `store` já nasce com o
 * limite, sem depender de quem declara a rota lembrar.
 */
final class AuthenticatedSessionController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['store'])];
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
