<?php

declare(strict_types=1);

namespace App\Demo\Accounts;

use App\Demo\Support\DemoSurface;
use Twstec\Kit\Auth\Contracts\LoginPrefillProvider;
use Twstec\Kit\Auth\Support\LoginPrefill;

/**
 * Credenciais das contas demo nas telas de login (ponto de extensão
 * Twstec\Kit\Auth\Contracts\LoginPrefillProvider).
 *
 * Quem decide se elas aparecem é o DemoSurface, não a flag crua: em
 * APP_ENV=production (sem DEMO_ALLOW_IN_PRODUCTION declarado) nada é
 * sugerido, mesmo que DEMO_LOGIN_ENABLED tenha ficado ligado no .env copiado
 * do exemplo. Entregar `admin@tws.dev` com a senha pública já digitada no
 * login do super admin é a forma mais curta de perder a instalação.
 *
 *   web   → usuário demo, com o aviso e as credenciais impressas na tela;
 *   admin → super admin demo, só preenchido (o /admin não imprime aviso).
 */
final class DemoLoginPrefill implements LoginPrefillProvider
{
    public function for(string $surface): ?LoginPrefill
    {
        if (! DemoSurface::loginEnabled()) {
            return null;
        }

        return match ($surface) {
            'web' => new LoginPrefill(
                (string) config('ui.demo_login.email'),
                (string) config('ui.demo_login.password'),
                notice: __('auth.ui.demo_notice'),
                label: __('auth.ui.demo_credentials'),
            ),
            'admin' => new LoginPrefill(
                (string) config('ui.demo_admin.email'),
                (string) config('ui.demo_admin.password'),
            ),
            default => null,
        };
    }
}
