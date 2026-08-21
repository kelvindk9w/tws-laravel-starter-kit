<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Core\Localization\Middleware\SetLocale;
use App\Core\Security\Middleware\EnsureAdminIpAllowed;
use App\Core\Security\Middleware\UseEvalBundleForAdmin;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Profile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Super admin Filament (/admin — ADR-011, Fase 6).
 *
 * - Acesso: SÓ usuários com is_admin + conta ativa (User::canAccessPanel —
 *   os demais recebem 403). Promoção exclusiva via `php artisan user:make-admin`.
 * - Branding 100% via platform() (ADR-007/010): nome, logo e cor primária
 *   vêm do .env — nada hardcoded.
 * - IP allowlist: EnsureAdminIpAllowed (config security.admin.allowed_ips,
 *   ADMIN_ALLOWED_IPS). Lista vazia = sem restrição (só desenvolvimento);
 *   em produção, preencher com os IPs fixos/VPN (checklist item 25).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Página própria: pré-preenche as credenciais do admin demo
            // quando o login demo está habilitado (só em local).
            ->login(Login::class)
            ->brandName(platform()->name)
            ->brandLogo(platform()->logoUrl)
            ->colors([
                'primary' => Color::hex(platform()->primaryColor),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            // Seletor de idioma na topbar (mesmo formato compacto do resto
            // do kit: bandeira + sigla). O locale é resolvido pelo SetLocale
            // abaixo (preferência da conta → cookie → padrão da plataforma).
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.topbar-locale-switcher')->render(),
            )
            // Perfil demo-safe no menu do usuário (nome editável, e-mail
            // read-only, seção de senha só prévia — ver Pages\Profile).
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => __('admin.profile.heading'))
                    ->url(fn (): string => Profile::getUrl())
                    ->icon(Heroicon::OutlinedUserCircle),
            ])
            ->middleware([
                // Bundle JS normal do Livewire (com eval) só no /admin — o
                // Filament 5 não funciona com o build CSP-safe do Alpine.
                UseEvalBundleForAdmin::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Locale do painel (ADR-007): mesma resolução do app —
                // preferência da conta → cookie → padrão da plataforma.
                SetLocale::class,
                // IP allowlist do super admin (ADR-011) — ver docblock acima.
                EnsureAdminIpAllowed::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
