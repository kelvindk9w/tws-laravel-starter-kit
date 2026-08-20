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

// =============================================================================
// Backups (Fase 7 — ADR-010): dump lógico do PostgreSQL (pg_dump) em zip
// criptografado → disco de destino (R2 em produção). O sucesso dispara o
// webhook da validação cruzada produção→sandbox (BACKUP_WEBHOOK_URL —
// endpoint receptor no sandbox; contrato no README). Frequências em cron
// (UTC) via .env; backup:clean aplica a retenção e backup:monitor é o
// health check de idade/tamanho. onOneServer/withoutOverlapping evitam
// execução dupla em deploys com múltiplos schedulers.
// =============================================================================
Schedule::command('backup:run --only-db')
    ->cron((string) env('BACKUP_RUN_CRON', '0 * * * *'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:clean')
    ->cron((string) env('BACKUP_CLEAN_CRON', '30 2 * * *'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:monitor')
    ->cron((string) env('BACKUP_MONITOR_CRON', '0 3 * * *'))
    ->withoutOverlapping()
    ->onOneServer();
