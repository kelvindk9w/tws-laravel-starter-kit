<?php

declare(strict_types=1);

use App\Core\ApiKeys\Http\Controllers\ApiKeyController;
use App\Core\Tenancy\Http\Controllers\ProjectController;
use App\Core\Uploads\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Foundation\Http\Controllers\HealthController;

// Saúde da aplicação (excluída do request log em banco — ver config/security.php
// e docs/logs-lgpd.md). Passa por validação de segurança, headers e rate limit global.
Route::get('/health', HealthController::class)->name('api.health');

// =============================================================================
// API v1 — Motor de API Keys + Tenancy.
//
// Autenticação (middleware resolve.tenant): par de credenciais no header —
//   X-Api-Key: pk_live_...            (chave pública)
//   Authorization: Bearer sk_live_... (chave secreta — só hash no banco)
// O tenant (dono da chave) é resolvido e vinculado ao request log; cada rota
// exige ainda o scope granular da chave (middleware scope:recurso:acao).
//
// Criação e rotação de chave são AÇÕES SENSÍVEIS: exigem o token
// de curta duração emitido pela confirmação sensível (senha de transação + 2FA por e-mail),
// enviado no header X-Sensitive-Action-Token.
// =============================================================================
Route::prefix('v1')->middleware('resolve.tenant')->name('api.v1.')->group(function (): void {
    // --- Chaves de API -------------------------------------------------------
    // Gerenciar chaves é operação de CONTA: exige chave sem vínculo a projetos
    // (account.key). Uma chave vinculada que pudesse gerenciar chaves se
    // desvincularia sozinha — o vínculo limita o scope, nunca o contrário.
    // Exceção: a chave vinculada pode rotacionar ou revogar a SI MESMA
    // (account.key:self) — nenhuma das duas amplia acesso.
    Route::middleware('account.key')->group(function (): void {
        Route::get('api-keys', [ApiKeyController::class, 'index'])
            ->middleware('scope:api-keys:read')
            ->name('api-keys.index');

        Route::post('api-keys', [ApiKeyController::class, 'store'])
            ->middleware(['scope:api-keys:create', 'sensitive.token'])
            ->name('api-keys.store');

        // Vínculo N:N chave ↔ projetos (lista vazia = chave enxerga a conta toda).
        Route::put('api-keys/{uuid}/projects', [ApiKeyController::class, 'syncProjects'])
            ->middleware('scope:api-keys:assign')
            ->name('api-keys.projects.sync');
    });

    Route::middleware('account.key:self')->group(function (): void {
        Route::delete('api-keys/{uuid}', [ApiKeyController::class, 'destroy'])
            ->middleware('scope:api-keys:revoke')
            ->name('api-keys.destroy');

        Route::post('api-keys/{uuid}/rotate', [ApiKeyController::class, 'rotate'])
            ->middleware(['scope:api-keys:rotate', 'sensitive.token'])
            ->name('api-keys.rotate');
    });

    // --- Projetos (camada organizacional) -------------------------------------
    // Chave vinculada a projetos só enxerga os vinculados (404 nos demais) e
    // não cria projeto (operação de conta — account.key).
    Route::get('projects', [ProjectController::class, 'index'])
        ->middleware('scope:projects:read')
        ->name('projects.index');

    Route::post('projects', [ProjectController::class, 'store'])
        ->middleware(['account.key', 'scope:projects:create'])
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

    // --- Uploads seguros ------------------------------------------------------
    // Função global única: validação de formulário (Form Request) → validação
    // de segurança do arquivo (magic bytes, polyglot, PDF c/ script) → upload.
    Route::post('uploads', [UploadController::class, 'store'])
        ->middleware('scope:uploads:create')
        ->name('uploads.store');
});
