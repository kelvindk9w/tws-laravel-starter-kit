<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Login do super admin (/admin). Quando o login demo está habilitado
 * (config('ui.demo_login.enabled') — padrão só em APP_ENV=local), as
 * credenciais do admin demo vêm pré-preenchidas: basta clicar em entrar.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (config('ui.demo_login.enabled')) {
            $this->form->fill([
                'email' => config('ui.demo_admin.email'),
                'password' => config('ui.demo_admin.password'),
                'remember' => true,
            ]);
        }
    }
}
