<?php

declare(strict_types=1);

// =============================================================================
// Webhooks de saída — configuração padrão do pacote twstec/kit-webhooks. A
// aplicação pode publicar a própria cópia (`vendor:publish
// --tag=webhooks-config`); as chaves de primeiro nível dela prevalecem.
//
// Política: o segredo de assinatura é gerado pelo servidor, mostrado UMA vez e
// guardado CIFRADO (APP_KEY); o corpo do evento fica cifrado no banco e o job
// da fila só carrega identificadores, também cifrados. O destino é conferido
// contra SSRF no cadastro E de novo na hora de cada envio (DNS resolvido de
// novo, conexão no IP conferido, sem redirect). Ver docs/webhooks.md.
// =============================================================================

return [

    // --- Catálogo de eventos ---------------------------------------------------
    // Os eventos que o APLICATIVO emite (`Webhooks::dispatch($conta,
    // 'order.created', [...])`). Um endpoint assina um subconjunto deles (ou
    // todos, com `*`). Evento fora do catálogo é RECUSADO no disparo (erro de
    // programação — nunca um envio silencioso). O rótulo de cada evento nas
    // telas vem da tradução `webhooks.events.<nome>` quando existe.
    // O evento de teste `webhook.ping` (botão "enviar teste") é do pacote e
    // não precisa estar aqui.
    'events' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_EVENTS', ''))))),

    // --- Destino (SSRF) ----------------------------------------------------------
    'destination' => [
        // HTTPS obrigatório. Em PRODUÇÃO vale sempre, mesmo com `false`
        // aqui (com aviso no log a cada boot). Fora de produção, `false`
        // aceita http:// — útil para um receptor local de desenvolvimento.
        'require_https' => env('WEBHOOKS_REQUIRE_HTTPS', true),

        // Redes privadas LIBERADAS fora de produção (CIDR, separados por
        // vírgula — ex.: a rede do Docker de desenvolvimento). Em PRODUÇÃO a
        // lista é IGNORADA (com aviso no log a cada boot): o destino de um
        // webhook é sempre um endereço público. Endereço de metadados de
        // nuvem nunca é liberado, nem com esta lista.
        'allowed_private_networks' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_ALLOWED_PRIVATE_NETWORKS', ''))))),

        // Tempo para conectar e tempo total do envio, em segundos.
        'connect_timeout' => (float) env('WEBHOOKS_CONNECT_TIMEOUT', 3),
        'timeout' => (float) env('WEBHOOKS_TIMEOUT', 10),
    ],

    // --- Segredo de assinatura ---------------------------------------------------
    'secret' => [
        // Convivência dos dois segredos depois de uma rotação: por quanto
        // tempo (no máximo) o segredo anterior continua assinando junto com o
        // novo — o receptor troca o dele sem perder evento.
        'max_overlap_minutes' => (int) env('WEBHOOKS_SECRET_MAX_OVERLAP_MINUTES', 10080),
        'default_overlap_minutes' => (int) env('WEBHOOKS_SECRET_DEFAULT_OVERLAP_MINUTES', 1440),
    ],

    // --- Entrega -----------------------------------------------------------------
    'delivery' => [
        // Espera antes de cada NOVA tentativa, em segundos (backoff
        // exponencial). Padrão: 1 min, 5 min, 30 min, 2 h, 12 h — seis
        // tentativas ao todo (a primeira e cinco novas).
        'backoff' => array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_BACKOFF', '60,300,1800,7200,43200'))), 'strlen'))),

        // Endpoint DESATIVADO depois de tantas tentativas falhas SEGUIDAS
        // (qualquer sucesso zera a conta), com aviso por e-mail ao dono e aos
        // administradores da conta e linha na trilha de auditoria.
        'disable_after_failures' => (int) env('WEBHOOKS_DISABLE_AFTER_FAILURES', 20),

        // Quanto da RESPOSTA do receptor fica no log de entregas (bytes,
        // redigido). O resto nem é lido.
        'response_excerpt_bytes' => (int) env('WEBHOOKS_RESPONSE_EXCERPT_BYTES', 1024),

        // Fila e conexão dos jobs de entrega (nulo = as padrão).
        'queue' => env('WEBHOOKS_QUEUE'),
        'connection' => env('WEBHOOKS_QUEUE_CONNECTION'),

        // Uma entrega "em andamento" cujo processo morreu volta a ser
        // tentada depois deste prazo (segundos). Precisa passar o timeout.
        'lock_seconds' => (int) env('WEBHOOKS_LOCK_SECONDS', 120),
    ],

    // --- Outbox ------------------------------------------------------------------
    // O evento é gravado no banco ANTES de ir para a fila. Se a fila cair, o
    // comando `webhooks:dispatch-pending` (agendado pelo pacote) põe de novo
    // na fila o que está vencido. Cron vazio desliga, com aviso a cada boot.
    'outbox' => [
        'schedule' => env('WEBHOOKS_OUTBOX_SCHEDULE', '* * * * *'),
        // Entrega já posta na fila só volta a ser posta depois disto (segundos).
        'requeue_after_seconds' => (int) env('WEBHOOKS_REQUEUE_AFTER_SECONDS', 300),
    ],

    // --- Retenção ----------------------------------------------------------------
    // `webhooks:prune` apaga eventos (com as entregas e tentativas) mais
    // velhos que `days` e já resolvidos. Cron vazio desliga, com aviso.
    'prune' => [
        'days' => (int) env('WEBHOOKS_PRUNE_DAYS', 30),
        'schedule' => env('WEBHOOKS_PRUNE_SCHEDULE', '20 4 * * *'),
    ],

];
