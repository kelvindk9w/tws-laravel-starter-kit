<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\SendPasswordResetLink;
use App\Core\Auth\Contracts\Responses\PasswordResetLinkSentResponse;
use App\Core\Auth\Http\Requests\ForgotPasswordRequest;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solicitação de link de redefinição de senha por e-mail — só HTTP.
 *
 * Anti-enumeração: a resposta (contrato PasswordResetLinkSentResponse) é
 * SEMPRE a mesma, existindo ou não o e-mail. A regra mora na Action
 * SendPasswordResetLink.
 */
final class PasswordResetLinkController
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, SendPasswordResetLink $send): Response
    {
        $send->handle($request->string('email')->toString());

        return app(PasswordResetLinkSentResponse::class)->toResponse($request);
    }
}
