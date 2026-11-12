<?php

declare(strict_types=1);

// =============================================================================
// Webhooks de saída e de entrada — configuração padrão do pacote twstec/kit-webhooks. O
// aplicativo só tem config/webhooks.php se mudar algo, com as chaves que muda
// de primeiro nível que muda — cada uma vence a do pacote inteira; para um
// valor solto, prefira a variável do .env (ver docs/personalizacao.md).
//
// Política: o segredo de assinatura é gerado pelo servidor, mostrado na
// criação (depois, só pela revelação — ação sensível, registrada na trilha) e
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
        // Ambientes em que as duas FOLGAS abaixo (http e redes privadas)
        // valem — lista explícita, separada por vírgula. Em QUALQUER outro
        // ambiente (production, staging, sandbox, um nome digitado errado...)
        // elas são IGNORADAS, com aviso no log a cada boot: falha fechada.
        'relaxed_environments' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_RELAXED_ENVIRONMENTS', 'local,testing'))))),

        // HTTPS obrigatório. `false` aceita http:// — útil para um receptor
        // local de desenvolvimento —, mas SÓ nos `relaxed_environments`.
        'require_https' => env('WEBHOOKS_REQUIRE_HTTPS', true),

        // Redes privadas LIBERADAS (CIDR, separados por vírgula — ex.: a rede
        // do Docker de desenvolvimento), SÓ nos `relaxed_environments`. Fora
        // deles a lista é IGNORADA: o destino de um webhook é sempre um
        // endereço público. Endereço de metadados de nuvem nunca é liberado,
        // nem com esta lista.
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

        // Endpoint DESATIVADO quando as DUAS condições valem: pelo menos
        // tantas tentativas falhas SEGUIDAS e falhando sem parar há pelo menos
        // tantas horas (qualquer sucesso zera as duas). Uma queda curta do
        // receptor, mesmo com muito volume, não desativa. Ao desativar: aviso
        // por e-mail ao dono e aos administradores da conta e linha na trilha.
        // As entregas não se perdem: ficam PAUSADAS e voltam ao reativar.
        // `disable_after_hours = 0` desativa só pela contagem.
        'disable_after_failures' => (int) env('WEBHOOKS_DISABLE_AFTER_FAILURES', 20),
        'disable_after_hours' => (int) env('WEBHOOKS_DISABLE_AFTER_HOURS', 72),

        // Quanto da RESPOSTA do receptor fica no log de entregas (bytes,
        // redigido). O resto nem é lido.
        'response_excerpt_bytes' => (int) env('WEBHOOKS_RESPONSE_EXCERPT_BYTES', 1024),

        // Fila e conexão dos jobs de entrega (nulo = as padrão).
        'queue' => env('WEBHOOKS_QUEUE'),
        'connection' => env('WEBHOOKS_QUEUE_CONNECTION'),

        // Uma entrega "em andamento" cujo processo morreu volta a ser
        // tentada depois deste prazo (segundos). O pacote nunca usa menos que
        // o tempo máximo do job (conexão + envio + folga): um valor menor
        // aqui é elevado sozinho, para dois workers nunca enviarem juntos.
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
    // velhos que `days` e já resolvidos — entrega PAUSADA (endpoint
    // desativado) conta como resolvida só depois desse prazo: dentro dele,
    // reativar o endpoint a envia; fora, ela sai com o evento. Cron vazio
    // desliga, com aviso.
    'prune' => [
        'days' => (int) env('WEBHOOKS_PRUNE_DAYS', 30),
        'schedule' => env('WEBHOOKS_PRUNE_SCHEDULE', '20 4 * * *'),
    ],

    // =========================================================================
    // Webhooks RECEBIDOS (entrada) — ver docs/webhooks-entrada.md.
    //
    // Uma rota para todas as fontes: `POST /<path>/<fonte>`, fora do grupo
    // `web` (sem sessão nem CSRF). Cada requisição passa por: fonte conhecida
    // (senão 404) → allowlist de IP da fonte (403) → tamanho do corpo (413) →
    // VERIFICAÇÃO sobre o corpo cru (401, sem o motivo) → deduplicação no
    // banco por (fonte, id do evento) → fila. Responde 202 (aceito) ou 200
    // (repetição). O corpo e os cabeçalhos de assinatura nunca vão para log
    // nem trilha; o caminho entra sozinho nos caminhos de corpo assinado do
    // filtro de ataques (security.validation.signed_body_paths).
    // =========================================================================
    'inbound' => [
        // `false` tira a rota (e o caminho dos caminhos de corpo assinado).
        'enabled' => (bool) env('WEBHOOKS_INBOUND_ENABLED', true),

        // O prefixo da rota: só segmentos literais (sem `*`).
        'path' => env('WEBHOOKS_INBOUND_PATH', 'webhooks/inbound'),

        // Maior corpo aceito, em bytes (o teto do filtro de ataques,
        // security.validation.max_inspected_bytes, vale antes). Cada fonte
        // pode baixar ou subir o seu (`max_body_bytes`).
        'max_body_bytes' => (int) env('WEBHOOKS_INBOUND_MAX_BODY_BYTES', 262144),

        // Janela do carimbo de tempo, em segundos, para os dois lados (velho
        // e futuro), nos esquemas que assinam um carimbo. Fonte: `tolerance`
        // dentro do `verifier`.
        'tolerance_seconds' => (int) env('WEBHOOKS_INBOUND_TOLERANCE_SECONDS', 300),

        // Requisições por minuto, por fonte e IP (fonte: `rate_limit_per_minute`).
        'rate_limit_per_minute' => (int) env('WEBHOOKS_INBOUND_RATE_LIMIT', 600),

        // Guardar o corpo decodificado no banco (CIFRADO, APP_KEY)? Padrão
        // NÃO: ele viaja só no job da fila, também cifrado. Ligado, é apagado
        // depois de `retention.payload_days`. Fonte: `store_payload`.
        'store_payload' => (bool) env('WEBHOOKS_INBOUND_STORE_PAYLOAD', false),

        // O job que chama o handler: tentativas, espera antes de cada nova
        // tentativa (segundos), tempo máximo, fila e conexão (nulo = as
        // padrão). Fonte: `tries`, `backoff`, `queue`, `connection`.
        'tries' => (int) env('WEBHOOKS_INBOUND_TRIES', 5),
        'backoff' => array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_INBOUND_BACKOFF', '10,60,300,1800'))), 'strlen'))),
        'timeout' => (int) env('WEBHOOKS_INBOUND_TIMEOUT', 60),
        'queue' => env('WEBHOOKS_INBOUND_QUEUE'),
        'connection' => env('WEBHOOKS_INBOUND_QUEUE_CONNECTION'),

        // `webhooks:prune-inbound`: o registro de deduplicação fica `days`
        // dias (a janela da deduplicação); o corpo guardado, `payload_days`.
        // Cron vazio desliga, com aviso no log a cada boot.
        'retention' => [
            'days' => (int) env('WEBHOOKS_INBOUND_RETENTION_DAYS', 30),
            'payload_days' => (int) env('WEBHOOKS_INBOUND_PAYLOAD_RETENTION_DAYS', 7),
            'schedule' => env('WEBHOOKS_INBOUND_PRUNE_SCHEDULE', '35 4 * * *'),
        ],

        // `webhooks:requeue-inbound`: aviso autêntico gravado com a fila fora
        // do ar é respondido com 202 e fica esperando (nunca 503, que faz o
        // provedor interromper a fila do endereço). A rodada põe na fila os
        // parados há mais de `after_seconds`, até `batch` por vez. Cron vazio
        // desliga, com aviso no log a cada boot.
        'requeue' => [
            'after_seconds' => (int) env('WEBHOOKS_INBOUND_REQUEUE_AFTER', 60),
            'batch' => (int) env('WEBHOOKS_INBOUND_REQUEUE_BATCH', 100),
            'schedule' => env('WEBHOOKS_INBOUND_REQUEUE_SCHEDULE', '* * * * *'),
        ],
    ],

    // --- Fontes de webhooks recebidos --------------------------------------------
    // Uma por provedor, pelo nome da URL (`/webhooks/inbound/<nome>`). Fica
    // VAZIO no pacote: o aplicativo declara as dele no config/webhooks.php
    // só com esta chave. Segredos sempre por variável de ambiente — dois por
    // fonte durante uma rotação (o atual e o anterior). Exemplo:
    //
    //     'inbound_sources' => [
    //         'pagamentos' => [
    //             'verifier' => [
    //                 'driver' => 'header_token',     // header_token | hmac | standard_webhooks | public_key | classe
    //                 'header' => 'x-webhook-token',
    //                 'secrets' => [env('PAGAMENTOS_WEBHOOK_TOKEN'), env('PAGAMENTOS_WEBHOOK_TOKEN_PREVIOUS')],
    //             ],
    //             'handler' => App\Webhooks\PagamentosHandler::class,
    //             'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated'],
    //             'allowed_ips' => array_filter(explode(',', (string) env('PAGAMENTOS_WEBHOOK_IPS', ''))),
    //         ],
    //     ],
    'inbound_sources' => [],

];
