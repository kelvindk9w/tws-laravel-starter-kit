<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\PasswordResetLinkSentResponse as PasswordResetLinkSentResponseContract;

/**
 * Padrão: volta ao formulário com a mesma mensagem, exista ou não o e-mail.
 */
final class PasswordResetLinkSentResponse implements PasswordResetLinkSentResponseContract
{
    public function toResponse(Request $request): Response
    {
        return back()->with('status', __('passwords.sent'));
    }
}
