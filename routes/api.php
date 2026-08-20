<?php

declare(strict_types=1);

use App\Core\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Saúde da aplicação (excluída do request log em banco — ver config/security.php
// e README). Passa por validação de segurança, headers e rate limit global.
Route::get('/health', HealthController::class)->name('api.health');
