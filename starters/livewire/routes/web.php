<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\ThemePreferenceController;
use App\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Livewire\Dashboard;
use App\Livewire\Notifications\Preferences as NotificationPreferences;
use App\Livewire\Profile;
use App\Livewire\Projects\Index as ProjectsIndex;
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
use Twstec\Kit\Uploads\Http\Controllers\AvatarController;

// Página inicial do PRODUTO. Uma extensão instalada pode responder por "/"
// com a própria página (a demonstração do kit responde com a landing): as
// rotas dela são carregadas antes deste arquivo e, nesse caso, esta não é
// registrada — nunca duas rotas para o mesmo endereço.
if (! array_key_exists('/', Route::getRoutes()->get('GET'))) {
    Route::view('/', 'home')->name('home');
}

// Troca de idioma: visitante → cookie; logado → também persiste
// na conta. Whitelist: platform()->availableLocales (fora dela = 404).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Pré-visualização dos e-mails transacionais (/mail-preview) — ferramenta de
// DESENVOLVIMENTO. Quem abre a galeria é o MailPreviewGate (padrão do produto:
// MAIL_PREVIEW_ENABLED, só em local, nunca em produção; com a demonstração
// instalada, o modo demo). Fechada, responde 404 — uma galeria pública com o
// desenho de todos os e-mails é presente de phishing.
Route::get('mail-preview/{slug?}', MailPreviewController::class)->name('mail.preview');

// =============================================================================
// Autenticação web (sessão).
//
// Implementação própria enxuta (sem Breeze/Jetstream/Fortify). As TELAS (GET)
// são do starter (AuthPageController + views em resources/views/auth); o envio
// de cada formulário vai para os controllers do pacote twstec/kit-auth, que
// trazem o próprio `throttle:sensitive` (config/security.php) — nenhuma rota
// sensível depende de alguém lembrar de declarar o limite aqui. CSRF é nativo
// do grupo `web`. Tudo protegido por `auth` exceto o explicitamente público
// (deny-by-default).
// =============================================================================

Route::middleware('guest')->group(function (): void {
    Route::get('register', [AuthPageController::class, 'register'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthPageController::class, 'login'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Segundo passo do login (verificação em duas etapas por e-mail). Fica
    // no `guest` porque quem está aqui AINDA NÃO está autenticado: acertou a
    // senha e tem só o estado intermediário na sessão (PendingTwoFactorLogin).
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
    Route::get('email/verify', [AuthPageController::class, 'verifyEmailNotice'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');
    Route::get('email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])
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
    Route::get('settings/transaction-password', [AuthPageController::class, 'transactionPassword'])
        ->name('transaction-password.edit');
    Route::put('settings/transaction-password', [TransactionPasswordController::class, 'update'])
        ->name('transaction-password.update');

    // Confirmação de ação sensível: senha de transação + código por e-mail
    // → token de ação sensível (curta duração, uso único).
    Route::post('sensitive-actions/code', [SensitiveActionController::class, 'store'])
        ->name('sensitive-actions.code');
    Route::post('sensitive-actions/confirm', [SensitiveActionController::class, 'confirm'])
        ->name('sensitive-actions.confirm');

    // Avatar do perfil: mesma função global de upload seguro da API
    // (SecureUploadService), restrita a imagens — re-encode GD antes de gravar.
    Route::post('settings/avatar', [AvatarController::class, 'update'])
        ->middleware('throttle:sensitive')
        ->name('settings.avatar');
});
