<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Core\Support\DemoSurface;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Login do super admin (/admin). Quando o login demo está habilitado
 * (DemoSurface::loginEnabled — flag DEMO_LOGIN_ENABLED, padrão só em
 * APP_ENV=local, E nunca em produção sem opt-out declarado), as credenciais do
 * admin demo vêm pré-preenchidas: basta clicar em entrar.
 *
 * Em produção o pré-preenchimento não acontece nem por engano: entregar
 * `admin@tws.dev` com a senha pública do .env.example já digitada no login do
 * super admin é a forma mais curta de perder a instalação.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (DemoSurface::loginEnabled()) {
            $this->form->fill([
                'email' => config('ui.demo_admin.email'),
                'password' => config('ui.demo_admin.password'),
                'remember' => true,
            ]);
        }
    }
}
