<?php

declare(strict_types=1);

use App\Core\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Core\Auth\Http\Controllers\NewPasswordController;
use App\Core\Auth\Http\Controllers\PasswordResetLinkController;
use App\Core\Auth\Http\Controllers\RegisteredUserController;
use App\Core\Auth\Http\Controllers\SensitiveActionController;
use App\Core\Auth\Http\Controllers\TransactionPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Senha de transação (hash separado da senha de login — ADR-006).
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
});
