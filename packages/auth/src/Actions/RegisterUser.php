<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Auth\Support\UserModel;

/**
 * Cadastro (implementação própria — sem starter kits de auth).
 *
 * Cria o usuário com identificadores externos automáticos (uuid +
 * codigo_publico USR-xxxx, via model) e inicia a sessão com session fixation
 * prevenido (regenerate). Com a verificação de e-mail ligada (padrão —
 * EmailVerification), a conta nasce SEM e-mail confirmado e o e-mail com o
 * link sai na hora. O idioma atual vira o idioma da conta, para esse primeiro
 * e-mail já chegar no idioma de quem se cadastrou.
 *
 * Os dados chegam JÁ VALIDADOS (RegisterRequest). A resposta é do contrato
 * RegisterResponse.
 */
final class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $input
     */
    public function handle(Request $request, array $input): AuthUser
    {
        $user = UserModel::name()::createWithPublicCodeRetry([
            'name' => $input['name'],
            'email' => $input['email'],
            // Cast 'hashed' do model aplica Argon2id (config/hashing.php).
            'password' => $input['password'],
            'locale' => app()->getLocale(),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        if (EmailVerification::required()) {
            EmailVerification::sendIfAllowed($user);
        }

        return $user;
    }
}
