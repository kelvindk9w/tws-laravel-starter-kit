<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Solicitação de link de redefinição de senha por e-mail.
 *
 * Anti-enumeração (checklist 11): a resposta é SEMPRE a mesma, existindo
 * ou não o e-mail cadastrado. O token do broker do Laravel já é armazenado
 * somente como hash e com expiração (config auth.passwords.users.expire).
 */
final class PasswordResetLinkController
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::sendResetLink($request->only('email'));

        // Resposta uniforme: não revela se o e-mail existe.
        return back()->with('status', __('passwords.sent'));
    }
}
