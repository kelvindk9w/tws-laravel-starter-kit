<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\PasswordResetLinkSentResponse as PasswordResetLinkSentResponseContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
