<?php

declare(strict_types=1);

use App\Core\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Core\Auth\Http\Controllers\EmailVerificationController;
use App\Core\Auth\Http\Controllers\NewPasswordController;
use App\Core\Auth\Http\Controllers\PasswordResetLinkController;
use App\Core\Auth\Http\Controllers\RegisteredUserController;
use App\Core\Auth\Http\Controllers\SensitiveActionController;
use App\Core\Auth\Http\Controllers\TransactionPasswordController;
use App\Core\Contact\Http\Controllers\ContactController;
use App\Core\Localization\Http\Controllers\LocaleController;
use App\Core\Mail\Http\Controllers\MailPreviewController;
use App\Core\Support\DemoSurface;
use App\Core\Uploads\Http\Controllers\AvatarController;
use App\Http\Controllers\LandingV2Controller;
use App\Http\Controllers\ShowcaseFormDemoController;
use App\Http\Controllers\ThemePreferenceController;
use App\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Livewire\Dashboard;
use App\Livewire\Notifications\Preferences as NotificationPreferences;
use App\Livewire\Profile;
use App\Livewire\Projects\Index as ProjectsIndex;
use Illuminate\Support\Facades\Route;

// Landing oficial do kit — a direção "Céu" venceu e virou a home. Página
// pública, sem estado e sem formulário próprio: uma view basta. Os números que
// ela exibe vêm de config/landing.php (nada hardcoded), as strings de
// lang/*/landing.php e os bundles próprios estão registrados no vite.config.js.
Route::view('/', 'landing')->name('landing');

// /v3 foi o endereço dessa mesma página enquanto ela era uma direção em
// avaliação. Links já compartilhados continuam valendo: 301 para a home (nunca
// 404 e nunca uma segunda URL servindo o mesmo conteúdo).
Route::permanentRedirect('v3', '/');

// Landing alternativa "O Rastro" (/v2) — mesma verdade do produto, outra
// direção de arte. Fica ao lado da oficial como conceito.
Route::get('v2', LandingV2Controller::class)->name('landing.v2');

// Formulário de contato da landing (público): honeypot + validação + rate
// limit de rotas sensíveis. E-mail enfileirado para PLATFORM_CONTACT_EMAIL.
Route::post('contato', [ContactController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('contact.store');

// Troca de idioma: visitante → cookie; logado → também persiste
// na conta. Whitelist: platform()->availableLocales (fora dela = 404).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Showcase de componentes UI (documentação viva do kit). Público apenas quando
// habilitado (config/ui.php ← UI_SHOWCASE_ENABLED; padrão: só em local) E fora
// de produção: em APP_ENV=production a vitrine responde 404 mesmo com a flag
// ligada, a não ser que DEMO_ALLOW_IN_PRODUCTION esteja declarado (DemoSurface).
//
// A rota continua REGISTRADA nos dois casos, respondendo 404: o rodapé e o menu
// do site geram route('ui.showcase') incondicionalmente, e desregistrar a rota
// derrubaria a home com RouteNotFoundException. 404 (e não 403) porque 403
// confirmaria que a vitrine existe e está a uma flag de distância de abrir.
Route::get('ui', function () {
    abort_unless(DemoSurface::showcaseEnabled(), 404);

    return view('showcase');
})->name('ui.showcase');

// Pré-visualização dos e-mails transacionais (/mail-preview) — ferramenta de
// DESENVOLVIMENTO, atrás da mesma flag do login demo (config/ui.php ←
// DEMO_LOGIN_ENABLED; padrão: só em local) E do fail-closed de produção
// (DemoSurface). Em produção responde 404 mesmo com a flag ligada — uma
// galeria pública com o desenho de todos os e-mails é presente de phishing.
Route::get('mail-preview/{slug?}', MailPreviewController::class)->name('mail.preview');

// Exemplo funcional do padrão Blade clássico (seção "Padrões de formulário"
// do /ui): POST + redirect + old() + erros. Mesma flag do showcase.
Route::post('ui/form-demo', [ShowcaseFormDemoController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('ui.form-demo');

// =============================================================================
// Autenticação web (sessão).
//
// Implementação própria enxuta (sem Breeze/Jetstream/Fortify). CSRF é nativo
// do grupo `web`; rotas sensíveis passam por `throttle:sensitive`
// (config/security.php). Tudo protegido por `auth` exceto o
// explicitamente público (deny-by-default).
// =============================================================================

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:sensitive');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:sensitive');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Preferência de tema do usuário logado (claro/escuro/sistema) — a
    // aplicação é instantânea via localStorage; aqui só persiste na conta.
    // Fora do `verified`: o seletor de tema também aparece na tela de aviso.
    Route::post('settings/theme', ThemePreferenceController::class)
        ->name('settings.theme');

    // Verificação de e-mail do cadastro (EmailVerificationController): a
    // saída de quem ainda não confirmou — por isso fora do `verified`. O link
    // do e-mail é validado no controller (assinatura relativa + expiração +
    // conta + hash do e-mail) para que link vencido volte ao aviso com a
    // explicação, não a uma página de erro.
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:sensitive')
        ->name('verification.send');
    Route::get('email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:sensitive')
        ->name('verification.verify');
});

// Tudo abaixo exige e-mail confirmado (`verified` — EnsureEmailIsVerified,
// que também vale para as ações Livewire destas páginas; desligável por
// AUTH_EMAIL_VERIFICATION_REQUIRED).
Route::middleware(['auth', 'verified'])->group(function (): void {
    // =====================================================================
    // Painel do usuário (Livewire 4).
    // UI direta: tudo se resolve na mesma tela, modais em vez de navegação.
    // =====================================================================
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('api-keys', ApiKeysIndex::class)->name('panel.api-keys');
    Route::get('projects', ProjectsIndex::class)->name('panel.projects');
    Route::get('notifications', NotificationPreferences::class)->name('panel.notifications');
    Route::get('profile', Profile::class)->name('panel.profile');

    // Senha de transação (hash separado da senha de login).
    // Rota standalone mantida; o painel Livewire (Perfil) usa o
    // MESMO TransactionPasswordService.
    Route::get('settings/transaction-password', [TransactionPasswordController::class, 'edit'])
        ->name('transaction-password.edit');
    Route::put('settings/transaction-password', [TransactionPasswordController::class, 'update'])
        ->middleware('throttle:sensitive')
        ->name('transaction-password.update');

    // Confirmação de ação sensível: senha de transação + código por e-mail
    // → token de ação sensível (curta duração, uso único).
    Route::post('sensitive-actions/code', [SensitiveActionController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('sensitive-actions.code');
    Route::post('sensitive-actions/confirm', [SensitiveActionController::class, 'confirm'])
        ->middleware('throttle:sensitive')
        ->name('sensitive-actions.confirm');

    // Avatar do perfil: mesma função global de upload seguro da API
    // (SecureUploadService), restrita a imagens — re-encode GD antes de gravar.
    Route::post('settings/avatar', [AvatarController::class, 'update'])
        ->middleware('throttle:sensitive')
        ->name('settings.avatar');
});
