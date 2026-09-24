<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\SendPasswordResetLink;
use Twstec\Kit\Auth\Contracts\Responses\PasswordResetLinkSentResponse;
use Twstec\Kit\Auth\Http\Requests\ForgotPasswordRequest;

/**
 * Solicitação de link de redefinição de senha por e-mail — só HTTP.
 *
 * Anti-enumeração: a resposta (contrato PasswordResetLinkSentResponse) é
 * SEMPRE a mesma, existindo ou não o e-mail. A regra mora na Action
 * SendPasswordResetLink. A tela é do front (no starter Livewire, a view
 * `auth.forgot-password`). O `throttle:sensitive` vem com o controller
 * (HasMiddleware).
 */
final class PasswordResetLinkController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['store'])];
    }

    public function store(ForgotPasswordRequest $request, SendPasswordResetLink $send): Response
    {
        $send->handle($request->string('email')->toString());

        return app(PasswordResetLinkSentResponse::class)->toResponse($request);
    }
}
