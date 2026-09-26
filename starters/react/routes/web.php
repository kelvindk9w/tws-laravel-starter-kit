<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\NotificationPreferencesController;
use App\Http\Controllers\Panel\PasswordController;
use App\Http\Controllers\Panel\ProfileController;
use App\Http\Controllers\Panel\TwoFactorPreferenceController;
use App\Http\Controllers\ThemePreferenceController;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Auth\Http\Controllers\AuthenticatedSessionController;
use Twstec\Kit\Auth\Http\Controllers\EmailVerificationController;
use Twstec\Kit\Auth\Http\Controllers\NewPasswordController;
use Twstec\Kit\Auth\Http\Controllers\PasswordResetLinkController;
use Twstec\Kit\Auth\Http\Controllers\RegisteredUserController;
use Twstec\Kit\Auth\Http\Controllers\SensitiveActionController;
use Twstec\Kit\Auth\Http\Controllers\TransactionPasswordController;
use Twstec\Kit\Auth\Http\Controllers\TwoFactorChallengeController;
use Twstec\Kit\Foundation\Localization\Http\Controllers\LocaleController;
use Twstec\Kit\Foundation\Mail\Http\Controllers\MailPreviewController;

// Página inicial do PRODUTO (mínima). Uma extensão instalada pode responder
// por "/" com a própria página: as rotas dela são carregadas antes deste
// arquivo e, nesse caso, esta não é registrada.
if (! array_key_exists('/', Route::getRoutes()->get('GET'))) {
    Route::inertia('/', 'welcome')->name('home');
}

// Troca de idioma: visitante → cookie; logado → também persiste na conta.
// Whitelist: platform()->availableLocales (fora dela = 404). O front abre o
// link com carga completa (a página volta inteira no idioma novo).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Pré-visualização dos e-mails transacionais (/mail-preview) — ferramenta de
// DESENVOLVIMENTO, fechada (404) fora dela (MailPreviewGate do foundation).
Route::get('mail-preview/{slug?}', MailPreviewController::class)->name('mail.preview');

// =============================================================================
// Autenticação web (sessão) — a do pacote twstec/kit-auth, não a do kit
// oficial (sem Fortify).
//
// As TELAS (GET) são do starter (AuthPageController → páginas Inertia); o
// ENVIO de cada formulário vai para os controllers do pacote, que trazem o
// próprio `throttle:sensitive` — nenhuma rota sensível depende de alguém
// lembrar de declarar o limite aqui. As respostas são as implementações
// Inertia dos contratos do pacote (App\Http\Responses\Inertia, registradas no
// AppServiceProvider). CSRF: nativo do grupo `web`; o Inertia manda o
// X-XSRF-TOKEN a cada envio. Os endereços e os nomes são os mesmos do
// starter Livewire.
// =============================================================================

Route::middleware('guest')->group(function (): void {
    Route::get('register', [AuthPageController::class, 'register'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthPageController::class, 'login'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Segundo passo do login: quem está aqui acertou a senha, mas AINDA NÃO
    // está autenticado (só há o estado intermediário na sessão).
    Route::get('two-factor-challenge', [AuthPageController::class, 'twoFactorChallenge'])
        ->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);
    Route::post('two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])
        ->name('two-factor.resend');
    Route::post('two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'destroy'])
        ->name('two-factor.cancel');

    Route::get('forgot-password', [AuthPageController::class, 'forgotPassword'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [AuthPageController::class, 'resetPassword'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Preferência de tema da conta (fora do `verified`: o tema também muda
    // na tela de aviso).
    Route::post('settings/theme', ThemePreferenceController::class)->name('settings.theme');

    // Verificação de e-mail do cadastro: a saída de quem ainda não confirmou
    // — por isso fora do `verified`.
    Route::get('email/verify', [AuthPageController::class, 'verifyEmailNotice'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');
    Route::get('email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');
});

// Tudo abaixo exige e-mail confirmado (`verified` — EnsureEmailIsVerified,
// do pacote; desligável por AUTH_EMAIL_VERIFICATION_REQUIRED).
Route::middleware(['auth', 'verified'])->group(function (): void {
    // =====================================================================
    // Painel do usuário (React + Inertia). As telas de contas, membros,
    // chaves de API e projetos (twstec/kit-accounts) e a foto de perfil
    // (twstec/kit-uploads) entram na F11b; o menu só mostra o que existe.
    // =====================================================================
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('panel.profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('panel.profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('panel.password.update');

    // Verificação em duas etapas (ação sensível, em dois envios).
    Route::post('profile/two-factor/code', [TwoFactorPreferenceController::class, 'code'])
        ->name('panel.two-factor.code');
    Route::put('profile/two-factor', [TwoFactorPreferenceController::class, 'update'])
        ->name('panel.two-factor.update');

    Route::get('notifications', [NotificationPreferencesController::class, 'edit'])->name('panel.notifications');
    Route::put('notifications', [NotificationPreferencesController::class, 'update'])->name('panel.notifications.update');

    // Senha de transação (hash separado da senha de login): a tela é do
    // starter, o envio é do pacote — o mesmo usado pelo perfil.
    Route::get('settings/transaction-password', [AuthPageController::class, 'transactionPassword'])
        ->name('transaction-password.edit');
    Route::put('settings/transaction-password', [TransactionPasswordController::class, 'update'])
        ->name('transaction-password.update');

    // Confirmação de ação sensível em JSON (para clientes próprios): senha de
    // transação + código por e-mail → token de ação sensível.
    Route::post('sensitive-actions/code', [SensitiveActionController::class, 'store'])
        ->name('sensitive-actions.code');
    Route::post('sensitive-actions/confirm', [SensitiveActionController::class, 'confirm'])
        ->name('sensitive-actions.confirm');
});
