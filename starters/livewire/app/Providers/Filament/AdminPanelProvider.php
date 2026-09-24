<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\EmailCodeAuthentication;
use App\Filament\Dashboards\DashboardRegistry;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Profile;
use App\Filament\Support\AdminAudit;
use App\Filament\Support\InitialsAvatarProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Twstec\Kit\Foundation\Localization\Middleware\SetLocale;
use Twstec\Kit\Foundation\Security\Middleware\EnsureAdminIpAllowed;
use Twstec\Kit\Foundation\Security\Middleware\UseEvalBundleForAdmin;
use Twstec\Kit\Foundation\Support\BrandMark;

/**
 * Super admin Filament (/admin).
 *
 * - Acesso: SÓ usuários com is_admin + conta ativa (User::canAccessPanel —
 *   os demais recebem 403). Promoção exclusiva via `php artisan user:make-admin`.
 * - Branding 100% via platform(): nome, logo e cor primária
 *   vêm do .env — nada hardcoded.
 * - Barreira de ORIGEM: EnsureAdminIpAllowed, o PRIMEIRO middleware da pilha
 *   (config security.admin.allowed_ips, ADMIN_ALLOWED_IPS). Lista vazia libera
 *   fora de produção; em produção sem allowlist declarada o painel RECUSA (403)
 *   — regra e justificativa em
 *   Twstec\Kit\Foundation\Security\AdminIpAllowlist.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Trilha de auditoria de ações: toda chamada Livewire de componente do
     * painel roda com um escopo de auditoria aberto — ver AdminAudit.
     */
    public function boot(): void
    {
        AdminAudit::register();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Página própria: pré-preenche as credenciais do admin demo
            // quando o login demo está habilitado (só em local).
            ->login(Login::class)
            // Toda Action e todo Criar/Salvar do painel roda numa transação.
            // É o que faz a trilha de auditoria falhar FECHADA: a linha de
            // `audit_events` é gravada dentro da mesma transação da mudança,
            // e se ela não puder ser gravada a mudança é desfeita (ver
            // Twstec\Kit\Foundation\Audit\AuditTrail). Halt/Cancel (recusa de guarda)
            // confirmam a transação — a tentativa recusada fica registrada.
            ->databaseTransactions()
            // Verificação em duas etapas no login, pelo mecanismo de MFA do
            // Filament com o MOTOR do kit: mesma preferência por conta do painel
            // do cliente, mesmo código por e-mail e mesmos limites (ver
            // EmailCodeAuthentication). Opcional — só pede o código de quem
            // ligou; ligar/desligar fica no perfil (Pages\Profile).
            ->multiFactorAuthentication([EmailCodeAuthentication::make()])
            ->brandName(platform()->name)
            // Marca: o .env sempre vence; sem ele, o kit tem uma
            // marca monocromática própria em vez de um quadrado preto.
            ->brandLogo(fn () => filled(platform()->logoUrl)
                ? (string) platform()->logoUrl
                : BrandMark::inlineSvg())
            ->brandLogoHeight('1.75rem')
            // Tipografia unificada (crítica de design #6): a MESMA voz do
            // painel do usuário e da landing. Self-hosted via
            // @fontsource-variable no tema Vite — nada de CDN de fonte
            // (a CSP do kit não permite font-src externo), por isso o
            // provider local sem URL: quem serve a fonte é o filament.css.
            ->font('Space Grotesk Variable', provider: LocalFontProvider::class)
            // Tema do painel com os tokens de identidade do kit
            // (resources/css/filament.css → theme.css).
            ->viteTheme('resources/css/filament.css')
            // Primária do painel: neutro puro, para casar com --color-brand
            // (quase-preto no claro / quase-branco no escuro). O Zinc antigo
            // dava um cinza-médio que não existia em nenhuma outra tela.
            ->colors([
                'primary' => platform()->primaryColor !== null
                    ? Color::hex(platform()->primaryColor)
                    : Color::Neutral,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // VARIANTES DE DASHBOARD (config/dashboards.php): o kit entrega
            // três dashboards nomeados — "Visão Geral", "Crescimento & API" e
            // "Conteúdo & Operação" — para o desenvolvedor escolher a base que
            // mais lhe agrada e adaptar, como fazem os temas de admin
            // clássicos. A variante padrão responde em /admin; as demais em
            // /admin/dashboards/{slug}. Desligar um slug em DASHBOARD_ENABLED
            // remove a página do menu E da rota (a classe nem é registrada) —
            // quem decide isso é o DashboardRegistry, nunca esta lista.
            ->pages(DashboardRegistry::pages())
            // ORDEM dos grupos do menu. Sem esta lista, o Filament ordena os
            // grupos pela ordem em que os itens são descobertos — e "Sistema"
            // acabava no topo, empurrando os dashboards para o rodapé da
            // barra lateral. Os rótulos são CLOSURES porque o painel é montado
            // antes do SetLocale: avaliar __() aqui congelaria o idioma padrão
            // e o casamento com o grupo declarado por cada resource falharia
            // em espanhol e inglês.
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.group_dashboards')),
                NavigationGroup::make(fn (): string => __('admin.nav.group_management')),
                NavigationGroup::make(fn (): string => __('admin.nav.group_catalog')),
                NavigationGroup::make(fn (): string => __('admin.nav.group_security')),
                NavigationGroup::make(fn (): string => __('admin.nav.group_system')),
            ])
            // Nenhum widget é registrado no PAINEL: cada variante declara os
            // seus em getWidgets(). Widget de painel apareceria em TODAS as
            // variantes de uma vez, que é o oposto de "escolha a sua".
            ->widgets([])
            // Seletor de idioma na topbar (mesmo formato compacto do resto
            // do kit: bandeira + sigla). O locale é resolvido pelo SetLocale
            // abaixo (preferência da conta → cookie → padrão da plataforma).
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.topbar-locale-switcher')->render(),
            )
            // Animação de entrada dos números dos dashboards (count-up leve,
            // desligada sozinha em prefers-reduced-motion). Ver o comentário
            // do próprio arquivo: é progressive enhancement — sem o script, os
            // números já estão na tela, corretos e formatados.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.dashboard-motion')->render(),
            )
            // Avatar do menu do usuário: foto de perfil de quem já subiu uma
            // (upload validado pelo SecureUploadService) e, sem foto, iniciais desenhadas
            // localmente em SVG. O provider de fábrica chama a ui-avatars.com
            // — CDN externa no caminho de toda página do painel só para
            // desenhar duas letras. Ver InitialsAvatarProvider.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Menu do usuário: perfil, alternador de tema
            // (claro/escuro/sistema — o Filament o injeta entre os itens de
            // sort negativo e os demais), voltar ao site e sair (o "Sair" é
            // acrescentado pelo próprio Filament no fim da lista).
            //
            // O seletor de IDIOMA continua na topbar, não aqui: trocar de
            // idioma é uma ação de leitura da tela inteira (decisão do dono).
            ->userMenuItems([
                // Perfil demo-safe (nome editável, e-mail read-only, seção
                // de senha só prévia — ver Pages\Profile).
                'profile' => MenuItem::make()
                    ->label(fn (): string => __('admin.profile.heading'))
                    ->url(fn (): string => Profile::getUrl())
                    ->icon(Heroicon::OutlinedUserCircle)
                    // Sort negativo = ANTES do alternador de tema.
                    ->sort(-1),
                'site' => MenuItem::make()
                    ->label(fn (): string => __('admin.menu.back_to_site'))
                    ->url(fn (): string => url('/'))
                    ->icon(Heroicon::OutlinedArrowLeftOnRectangle),
            ])
            // Barreira de ORIGEM — PRIMEIRA da pilha de propósito:
            // requisição de origem não permitida é recusada antes de a sessão
            // ser aberta, o CSRF processado ou o painel montado. Ela estava no
            // FIM da lista, o que fazia um IP barrado ainda ganhar cookie de
            // sessão e passar por todo o pipeline do painel para só então
            // receber 403. Barreira externa tem de ser externa. Ver
            // AdminIpAllowlist.
            //
            // PERSISTENTE (segundo argumento): as AÇÕES dos componentes do
            // painel não chegam pelas rotas /admin/..., e sim pelo endpoint
            // de atualização do Livewire — uma rota única, fora deste prefixo,
            // que também atende o painel do usuário e NÃO carrega esta pilha.
            // Ali o Livewire só reaplica os middlewares marcados como
            // persistentes, e só para componentes cuja rota de origem (gravada
            // no snapshot assinado) os declarava. Sem isto, a allowlist valia
            // para abrir a página, mas não para executar a ação dela: de fora
            // da lista, um snapshot obtido de dentro continuava operando o
            // painel (inclusive a tela de login). O Authenticate do Filament
            // (is_admin + conta ativa) já é persistente por padrão do pacote.
            //
            // Declarada em chamada PRÓPRIA porque o flag vale para a lista
            // inteira passada — os middlewares de sessão/cookie abaixo não
            // podem ser reexecutados no endpoint do Livewire.
            ->middleware([EnsureAdminIpAllowed::class], isPersistent: true)
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
                // Locale do painel: mesma resolução do app —
                // preferência da conta → cookie → padrão da plataforma.
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
