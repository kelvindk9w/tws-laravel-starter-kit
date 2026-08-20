<?php

declare(strict_types=1);

use App\Core\ApiKeys\Http\Controllers\ApiKeyController;
use App\Core\Http\Controllers\HealthController;
use App\Core\Tenancy\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

// Saúde da aplicação (excluída do request log em banco — ver config/security.php
// e README). Passa por validação de segurança, headers e rate limit global.
Route::get('/health', HealthController::class)->name('api.health');

// =============================================================================
// API v1 — Motor de API Keys + Tenancy (Fase 4 — ADR-005/006/010).
//
// Autenticação (middleware resolve.tenant): par de credenciais no header —
//   X-Api-Key: pk_live_...            (chave pública)
//   Authorization: Bearer sk_live_... (chave secreta — só hash no banco)
// O tenant (dono da chave) é resolvido e vinculado ao request log; cada rota
// exige ainda o scope granular da chave (middleware scope:recurso:acao).
//
// Criação e rotação de chave são AÇÕES SENSÍVEIS (ADR-010): exigem o token
// de curta duração emitido pela Fase 3 (senha de transação + 2FA por e-mail),
// enviado no header X-Sensitive-Action-Token.
// =============================================================================
Route::prefix('v1')->middleware('resolve.tenant')->name('api.v1.')->group(function (): void {
    // --- Chaves de API -------------------------------------------------------
    Route::get('api-keys', [ApiKeyController::class, 'index'])
        ->middleware('scope:api-keys:read')
        ->name('api-keys.index');

    Route::post('api-keys', [ApiKeyController::class, 'store'])
        ->middleware(['scope:api-keys:create', 'sensitive.token'])
        ->name('api-keys.store');

    Route::delete('api-keys/{uuid}', [ApiKeyController::class, 'destroy'])
        ->middleware('scope:api-keys:revoke')
        ->name('api-keys.destroy');

    Route::post('api-keys/{uuid}/rotate', [ApiKeyController::class, 'rotate'])
        ->middleware(['scope:api-keys:rotate', 'sensitive.token'])
        ->name('api-keys.rotate');

    // Vínculo N:N chave ↔ projetos (lista vazia = chave enxerga a conta toda).
    Route::put('api-keys/{uuid}/projects', [ApiKeyController::class, 'syncProjects'])
        ->middleware('scope:api-keys:assign')
        ->name('api-keys.projects.sync');

    // --- Projetos (camada organizacional — ADR-005) ---------------------------
    Route::get('projects', [ProjectController::class, 'index'])
        ->middleware('scope:projects:read')
        ->name('projects.index');

    Route::post('projects', [ProjectController::class, 'store'])
        ->middleware('scope:projects:create')
        ->name('projects.store');

    Route::get('projects/{uuid}', [ProjectController::class, 'show'])
        ->middleware('scope:projects:read')
        ->name('projects.show');

    Route::put('projects/{uuid}', [ProjectController::class, 'update'])
        ->middleware('scope:projects:update')
        ->name('projects.update');

    Route::delete('projects/{uuid}', [ProjectController::class, 'destroy'])
        ->middleware('scope:projects:delete')
        ->name('projects.destroy');
});
