<?php

declare(strict_types=1);

use App\Core\Auth\Models\SensitiveActionToken;
use App\Core\Auth\Models\VerificationCode;
use App\Core\Logging\Models\RequestLog;
use App\Core\Settings\Models\Setting;

// =============================================================================
// Trilha de auditoria de AÇÕES (`audit_events`) — quem fez o quê, em qual
// registro, mudando o quê. Ver App\Core\Audit\AuditTrail e docs/logs-lgpd.md.
// =============================================================================

return [

    // Retenção em DIAS. O `audit:prune` roda diariamente (routes/console.php)
    // e apaga o que aconteceu antes desta janela — é a ÚNICA remoção que a
    // tabela aceita. 0 = nunca podar (a tabela cresce para sempre: decisão
    // consciente, para quem tem obrigação legal de guarda maior).
    // Padrão: 365 dias.
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    // Models que a captura AUTOMÁTICA ignora, cada um por um motivo:
    // - RequestLog: é a outra trilha (e só muda no ciclo de vida da própria
    //   requisição, nunca por ação de admin);
    // - Setting: o SettingsManager registra a mudança ele mesmo, com a chave
    //   e o de/para legíveis (`setting.changed`) — a linha crua do model
    //   (`{"v": 10}`) só duplicaria a informação;
    // - VerificationCode / SensitiveActionToken: credenciais temporárias do
    //   fluxo de confirmação. O que se audita é o RESULTADO do fluxo (ex.:
    //   `user.two_factor_enabled`), não o código que o autorizou.
    // Acrescentar um model aqui tira as escritas dele da trilha — o teste de
    // arquitetura exige que resource de /admin com escrita não esteja aqui.
    'ignored_models' => [
        RequestLog::class,
        Setting::class,
        VerificationCode::class,
        SensitiveActionToken::class,
    ],

];
