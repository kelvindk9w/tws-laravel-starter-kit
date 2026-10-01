<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\RegisterUser;
use Twstec\Kit\Auth\Contracts\Responses\RegisterResponse;
use Twstec\Kit\Auth\Http\Requests\RegisterRequest;
use Twstec\Kit\Auth\Support\Registration;

/**
 * Registro de usuário — só HTTP.
 *
 * A regra (criar a conta com identificadores, idioma, sessão regenerada,
 * e-mail de verificação) mora na Action RegisterUser; a resposta vem do
 * contrato RegisterResponse (padrão: tela de aviso da verificação de e-mail,
 * ou o dashboard com ela desligada). A tela é do front (no starter Livewire,
 * a view `auth.register`). O `throttle:sensitive` vem com o controller
 * (HasMiddleware).
 *
 * Com o cadastro fechado (AUTH_REGISTRATION_ENABLED=false — Support\Registration),
 * o envio responde 404 ANTES da validação: nem a mensagem de "e-mail já
 * cadastrado" sai (o fechado não vira oráculo de contas existentes).
 */
final class RegisteredUserController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(static function (Request $request, Closure $next): Response {
                Registration::ensureOpen();

                return $next($request);
            }, only: ['store']),
            new Middleware('throttle:sensitive', only: ['store']),
        ];
    }

    public function store(RegisterRequest $request, RegisterUser $register): Response
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $request->validated();

        $user = $register->handle($request, $validated);

        return app(RegisterResponse::class)->toResponse($request, $user);
    }
}
