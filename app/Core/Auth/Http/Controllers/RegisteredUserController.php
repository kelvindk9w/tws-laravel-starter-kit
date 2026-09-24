<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\RegisterUser;
use App\Core\Auth\Contracts\Responses\RegisterResponse;
use App\Core\Auth\Http\Requests\RegisterRequest;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro de usuário — só HTTP.
 *
 * A regra (criar a conta com identificadores, idioma, sessão regenerada,
 * e-mail de verificação) mora na Action RegisterUser; a resposta vem do
 * contrato RegisterResponse (padrão: tela de aviso da verificação de e-mail,
 * ou o dashboard com ela desligada).
 */
final class RegisteredUserController
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterUser $register): Response
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $request->validated();

        $user = $register->handle($request, $validated);

        return app(RegisterResponse::class)->toResponse($request, $user);
    }
}
