<?php

declare(strict_types=1);

use App\Core\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Core\Auth\Http\Controllers\NewPasswordController;
use App\Core\Auth\Http\Controllers\PasswordResetLinkController;
use App\Core\Auth\Http\Controllers\RegisteredUserController;
use App\Core\Auth\Http\Controllers\SensitiveActionController;
use App\Core\Auth\Http\Controllers\TransactionPasswordController;
use App\Core\Contact\Http\Controllers\ContactController;
use App\Core\Localization\Http\Controllers\LocaleController;
use App\Core\Uploads\Http\Controllers\AvatarController;
use App\Http\Controllers\ShowcaseFormDemoController;
use App\Http\Controllers\ThemePreferenceController;
use App\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Livewire\Dashboard;
use App\Livewire\Notifications\Preferences as NotificationPreferences;
use App\Livewire\Profile;
use App\Livewire\Projects\Index as ProjectsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing');

// Formulário de contato da landing (público): honeypot + validação + rate
// limit de rotas sensíveis. E-mail enfileirado para PLATFORM_CONTACT_EMAIL.
Route::post('contato', [ContactController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('contact.store');

// Troca de idioma (ADR-007): visitante → cookie; logado → também persiste
// na conta. Whitelist: platform()->availableLocales (fora dela = 404).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Showcase de componentes UI (documentação viva do kit). Público apenas quando
// habilitado (config/ui.php ← UI_SHOWCASE_ENABLED; padrão: só em local). Fora
// isso responde 404 — em produção deve estar desabilitado (ver .env.example).
Route::get('ui', function () {
    abort_unless(config('ui.showcase_enabled'), 404);

    return view('showcase');
})->name('ui.showcase');

// Exemplo funcional do padrão Blade clássico (seção "Padrões de formulário"
// do /ui): POST + redirect + old() + erros. Mesma flag do showcase.
Route::post('ui/form-demo', [ShowcaseFormDemoController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('ui.form-demo');

// =============================================================================
// Autenticação web (sessão) — Fase 3 (ADR-006/010).
//
// Implementação própria enxuta (sem Breeze/Jetstream/Fortify). CSRF é nativo
// do grupo `web` (checklist 23); rotas sensíveis passam por `throttle:sensitive`
// (config/security.php — checklist 10). Tudo protegido por `auth` exceto o
// explicitamente público (deny-by-default — checklist 13).
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

    // =====================================================================
    // Painel do usuário (Livewire 4 — Fase 6, ADR-005/011).
    // UI direta: tudo se resolve na mesma tela, modais em vez de navegação.
    // =====================================================================
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('api-keys', ApiKeysIndex::class)->name('panel.api-keys');
    Route::get('projects', ProjectsIndex::class)->name('panel.projects');
    Route::get('notifications', NotificationPreferences::class)->name('panel.notifications');
    Route::get('profile', Profile::class)->name('panel.profile');

    // Preferência de tema do usuário logado (claro/escuro/sistema) — a
    // aplicação é instantânea via localStorage; aqui só persiste na conta.
    Route::post('settings/theme', ThemePreferenceController::class)
        ->name('settings.theme');

    // Senha de transação (hash separado da senha de login — ADR-006).
    // Rota standalone mantida da Fase 3; o painel Livewire (Perfil) usa o
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

    // Avatar do perfil (Fase 5): mesma função global de upload seguro da API
    // (SecureUploadService), restrita a imagens — re-encode GD antes de gravar.
    Route::post('settings/avatar', [AvatarController::class, 'update'])
        ->middleware('throttle:sensitive')
        ->name('settings.avatar');
});
