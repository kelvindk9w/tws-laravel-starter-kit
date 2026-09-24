<?php

declare(strict_types=1);

use App\Core\Uploads\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Foundation\Http\Controllers\HealthController;

// Saúde da aplicação (excluída do request log em banco — ver config/security.php
// e docs/logs-lgpd.md). Passa por validação de segurança, headers e rate limit global.
Route::get('/health', HealthController::class)->name('api.health');

// =============================================================================
// API v1.
//
// As rotas de chaves de API e de projetos (/api/v1/api-keys…, /api/v1/projects…)
// vêm do pacote twstec/kit-accounts, com a autenticação por chave
// (resolve.tenant), os escopos e o limite por chave — ver
// Twstec\Kit\Accounts\Http\ApiRoutes e docs/api.md.
//
// Aqui fica o que ainda é do aplicativo, no MESMO grupo (prefixo v1,
// resolve.tenant, nomes api.v1.*): o upload seguro, que vai para o pacote de
// uploads numa fase futura.
// =============================================================================
Route::prefix('v1')->middleware('resolve.tenant')->name('api.v1.')->group(function (): void {
    // --- Uploads seguros ------------------------------------------------------
    // Função global única: validação de formulário (Form Request) → validação
    // de segurança do arquivo (magic bytes, polyglot, PDF c/ script) → upload.
    Route::post('uploads', [UploadController::class, 'store'])
        ->middleware('scope:uploads:create')
        ->name('uploads.store');
});
