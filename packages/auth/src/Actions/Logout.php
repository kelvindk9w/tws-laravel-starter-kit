<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Encerra a sessão web: desautentica, invalida a sessão e renova o token
 * CSRF. A resposta é do contrato LogoutResponse.
 */
final class Logout
{
    public function handle(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
