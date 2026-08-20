<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\RegisterRequest;
use App\Core\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Registro de usuário (implementação própria — sem starter kits de auth).
 *
 * Cria o usuário com identificadores externos automáticos (uuid +
 * codigo_publico USR-xxxx, via model) e inicia a sessão com
 * session fixation prevenido (regenerate — checklist 22).
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
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('status', __('auth.registered'));
    }
}
