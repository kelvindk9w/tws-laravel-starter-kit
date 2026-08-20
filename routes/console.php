<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =============================================================================
// Expiração de chaves de API por inatividade (ADR-006): diário, em UTC.
// Aviso prévio por e-mail + desativação — ver ProcessApiKeyInactivity e
// config/api_keys.php (API_KEYS_INACTIVITY_*). onOneServer/withoutOverlapping
// evitam execução dupla em deploys com múltiplos schedulers.
// =============================================================================
Schedule::command('api-keys:process-inactivity')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
