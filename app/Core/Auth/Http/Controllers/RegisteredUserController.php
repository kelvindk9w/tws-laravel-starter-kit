<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\RegisterRequest;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Registro de usuário (implementação própria — sem starter kits de auth).
 *
 * Cria o usuário com identificadores externos automáticos (uuid +
 * codigo_publico USR-xxxx, via model) e inicia a sessão com
 * session fixation prevenido (regenerate).
 *
 * Com a verificação de e-mail ligada (padrão — EmailVerification), a conta
 * nasce SEM e-mail confirmado: o e-mail com o link sai na hora e a pessoa vai
 * à tela de aviso, não ao painel. O idioma escolhido no site vira o idioma da
 * conta, para esse primeiro e-mail já chegar no idioma de quem se cadastrou.
 */
final class RegisteredUserController
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $request->validated();

        $user = User::createWithPublicCodeRetry([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Cast 'hashed' do model aplica Argon2id (config/hashing.php).
            'password' => $validated['password'],
            'locale' => app()->getLocale(),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        if (EmailVerification::required()) {
            EmailVerification::sendIfAllowed($user);

            return redirect()
                ->route('verification.notice')
                ->with('status', __('auth.email_verification.registered'));
        }

        return redirect()
            ->route('dashboard')
            ->with('status', __('auth.registered'));
    }
}
