<?php

declare(strict_types=1);

// =============================================================================
// Segurança HTTP e pipeline de logs de requisição (ADR-004/005/010).
//
// Todos os valores são ajustáveis por .env — NUNCA hardcodar no código
// (ADR-007). Referência: checklist de segurança da pesquisa de stack (§3).
// =============================================================================

return [

    // --- Headers HTTP de segurança (OWASP Secure Headers — item 20) -----------
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),

        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),

        // CSP básica. Endurecer em produção (remover 'unsafe-inline' com nonces)
        // quando o frontend estiver pronto para isso.
        'content_security_policy' => env(
            'SECURITY_CSP',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; "
            ."frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        ),

        // CSP do super admin (/admin — Filament): o Filament 5 usa expressões
        // Alpine incompatíveis com o build CSP-safe (sem eval) — os modais e
        // ações não abrem sem 'unsafe-eval'. DECISÃO DOCUMENTADA: 'unsafe-eval'
        // é adicionado SOMENTE às rotas /admin* (painel interno, restrito por
        // is_admin + IP allowlist em produção). O painel do usuário e a API
        // seguem com a CSP estrita acima (Livewire em modo csp_safe).
        // Vazio = CSP padrão + 'unsafe-eval' automático no script-src.
        'content_security_policy_admin' => env('SECURITY_CSP_ADMIN', ''),

        // CSP do Horizon (/horizon — Fase 7): a SPA Vue do dashboard usa
        // template in-DOM ('unsafe-eval') e fontes do fonts.bunny.net.
        // Vazio = CSP base + derivações automáticas (ver SecurityHeaders).
        // Aplica-se SOMENTE às rotas do Horizon (is_admin + IP allowlist).
        'content_security_policy_horizon' => env('SECURITY_CSP_HORIZON', ''),

        // CSP da landing alternativa (/v2): a única diferença para a CSP
        // base é o connect-src — essa página lê a contagem de estrelas
        // do repositório direto na API pública do GitHub (client-side, com
        // fallback silencioso). Nenhum script de CDN, nenhum 'unsafe-eval',
        // nenhuma exceção de style/font: só o host de dados, e só nessas
        // rotas. Vazio = CSP base + esse host no connect-src.
        'content_security_policy_landing_alt' => env('SECURITY_CSP_LANDING_ALT', ''),

        // Hosts liberados no connect-src da landing alternativa (/v2).
        'landing_alt_connect_src' => array_filter(explode(',', (string) env('SECURITY_LANDING_ALT_CONNECT_SRC', 'https://api.github.com'))),

        // HSTS: só enviado sob HTTPS e quando habilitado (padrão: produção).
        'hsts_enabled' => env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
    ],

    // --- Quem pode dizer QUEM É O CLIENTE (proxies confiáveis) -----------------
    // Atrás de CDN/load balancer, o endereço da conexão é o do proxy: sem esta
    // declaração a allowlist do /admin compara o IP errado, o rate limiting
    // agrupa o mundo num balde só, a trilha de auditoria grava sempre o mesmo
    // `ip` e o HTTPS deixa de ser detectado. A regra inteira e a justificativa
    // das decisões estão em App\Core\Http\TrustedProxies.
    'proxies' => [
        // Proxies em que a aplicação confia para reescrever origem e esquema.
        // Lista separada por vírgula em TRUSTED_PROXIES, aceitando IP exato,
        // faixa CIDR IPv4/IPv6 e três palavras:
        //   `private`     → faixas privadas (RFC 1918/4193) + loopback. É o caso
        //                   do compose do kit: o nginx conversa com o php-fpm
        //                   pela rede interna, com IP que o Docker atribui.
        //   `REMOTE_ADDR` → confia em quem estiver conectando (mais estreito que
        //                   `private` quando o único caminho é o proxy).
        //   `*`           → confia em QUALQUER origem. Opt-out declarado: sem
        //                   valor padrão, ausente de todo arquivo de exemplo, e
        //                   com aviso no log a cada boot em produção.
        //
        // VAZIO (padrão) = nenhum proxy confiável: os headers de encaminhamento
        // são IGNORADOS e `ip()` é o endereço da conexão TCP. Pode estar errado
        // atrás de proxy, mas não é inseguro — ninguém consegue se declarar
        // outra pessoa. Por isso aqui o silêncio NÃO recusa o boot: ao contrário
        // da allowlist do admin, ele já cai para o lado estreito.
        //
        // ATENÇÃO: declarar proxy confiável exige que a BORDA acrescente o
        // endereço real ao X-Forwarded-For (no nginx,
        // `$proxy_add_x_forwarded_for` — já é o que os dois arquivos de nginx do
        // kit fazem). Proxy que só repassa o header do cliente, declarado
        // confiável, permite forjar IP.
        'trusted' => array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))),

        // Obedecer também `X-Forwarded-Host`? Padrão NÃO, ao contrário do padrão
        // do Laravel: esse header reescreve o host da aplicação, e o host monta
        // toda URL absoluta gerada (redirect, link de e-mail, URL assinada).
        // Ligue só quando a aplicação é servida num host interno e publicada em
        // outro — e nesse caso a lista de `hosts` abaixo é a barreira que
        // continua valendo.
        'trust_forwarded_host' => (bool) env('TRUSTED_PROXY_TRUST_FORWARDED_HOST', false),
    ],

    // --- Quais valores de `Host` a aplicação aceita ----------------------------
    // O Laravel monta toda URL absoluta a partir do header `Host`, que é dado do
    // cliente: sem esta validação, `Host: evil.example.com` sai refletido no
    // `Location` de qualquer redirect. A regra inteira está em
    // App\Core\Http\TrustedHosts.
    'hosts' => [
        // Hosts aceitos ALÉM do host da APP_URL (que já entra sozinho, com seus
        // subdomínios). Lista separada por vírgula, host exato
        // (`app.exemplo.com`) ou `*.exemplo.com` para o domínio e seus
        // subdomínios. Sem porta: a comparação é só do host. `localhost`,
        // `127.0.0.1` e `[::1]` são sempre aceitos (sondas e healthchecks
        // internos batem no /up por dentro).
        //
        // VAZIO (padrão) NÃO significa "qualquer host": significa o host da
        // APP_URL. Use esta lista para domínio com e sem `www`, domínio de
        // staging na mesma instalação, host interno do load balancer — ou, em
        // desenvolvimento, o IP do WSL / um `*.test` pelo qual se acesse.
        //
        // A validação vale em TODO ambiente, sem chave para desligar: ver o
        // bloco "vale em todo ambiente" em App\Core\Http\TrustedHosts.
        'trusted' => array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', '')))),
    ],

    // --- Segredos que não podem ser inventados nem ter padrão ------------------
    // Em produção, a aplicação RECUSA subir sem uma chave de aplicação
    // utilizável e AVISA no log quando um segredo de infraestrutura está com
    // valor de fachada. A regra e a justificativa das duas respostas diferentes
    // estão em App\Core\Support\CriticalSecrets.
    'secrets' => [
        // Valores reconhecidos como "depois eu troco". A lista existe porque
        // cada instalação tem o seu histórico de placeholder; o padrão já traz
        // os que o próprio kit ofereceu algum dia. A comparação ignora
        // maiúsculas e o prefixo `base64:`, e vale para o valor INTEIRO —
        // `troque-esta-senha` é placeholder, `troque-esta-senha-7f3a` não é.
        // NÃO existe variável para DESLIGAR a recusa da chave: não há
        // instalação de produção legítima cuja chave de criptografia seja um
        // valor público.
        'placeholders' => array_filter(array_map('trim', explode(',', (string) env(
            'SECURITY_SECRETS_PLACEHOLDERS',
            'troque-esta-senha,troque-esta-chave,mude-esta-senha,change-me,changeme,change-this,'
            .'senha,password,secret,segredo,example,exemplo,placeholder,todo,tbd',
        )))),

        // Comandos que fazem o processo PROCESSAR TRABALHO — ler e gravar dado
        // real, como o HTTP faz. Só nestes (e em qualquer processo que atenda
        // requisição) a chave inutilizável RECUSA o boot; nos demais comandos
        // ela apenas avisa, para que `composer install`, `package:discover`,
        // `config:cache`, `vendor:publish` e `migrate` não sejam derrubados —
        // sem `.env`, o Laravel resolve APP_ENV como `production`, então uma
        // recusa larga quebraria o CI, o build da imagem e o primeiro clone.
        //
        // A lista é de quem PROCESSA, e não de quem é liberado, porque uma
        // lista de liberados tem o padrão errado: todo comando de manutenção
        // novo (do Laravel, do Filament, do Horizon) voltaria a quebrar build
        // até alguém lembrar de incluí-lo. Esta lista é definida pelo DEPLOY —
        // são os nomes escritos no docker-compose.prod.yml — e por isso é
        // estável. Aceita `*` no fim para worker próprio (`meu-worker:*`).
        'processing_commands' => array_filter(array_map('trim', explode(',', (string) env(
            'SECURITY_SECRETS_PROCESSING_COMMANDS',
            'queue:work,queue:listen,horizon,horizon:work,horizon:supervisor,'
            .'schedule:run,schedule:work',
        )))),
    ],

    // --- Redirecionamento "de volta" (open redirect) ---------------------------
    // Toda rota que devolve o usuário ao endereço anterior (alternador de
    // idioma, `back()`, parâmetros `?redirect=`) só pode redirecionar para
    // DENTRO da aplicação — ver App\Core\Http\SafeRedirect.
    'redirects' => [
        // Destino usado quando o endereço pedido é externo, ausente ou
        // malformado. Caminho relativo à raiz ou URL de mesma origem.
        'fallback' => (string) env('SECURITY_REDIRECT_FALLBACK', '/'),

        // Origens ACEITAS além da própria APP_URL (esquema://host[:porta]),
        // separadas por vírgula. Use quando a aplicação atende por mais de um
        // endereço legítimo — domínio com e sem `www`, domínio de staging,
        // APP_URL em http enquanto a borda entrega em https. A origem é
        // comparada de forma estruturada (esquema + host + porta) e NUNCA é
        // lida do header `Host` da requisição, que é dado do cliente.
        'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('SECURITY_REDIRECT_ALLOWED_ORIGINS', '')))),
    ],

    // --- Rate limiting (item 10) — requisições por minuto ----------------------
    // Aplicado por usuário autenticado ou, na ausência, por IP.
    'rate_limit' => [
        // Global da API (grupo api inteiro).
        'api' => (int) env('RATE_LIMIT_API', 60),

        // Rotas sensíveis (login, códigos 2FA/verificação, recuperação de senha):
        // middleware throttle:sensitive.
        'sensitive' => (int) env('RATE_LIMIT_SENSITIVE', 5),
    ],

    // --- Superfícies administrativas (/admin — Filament, /horizon) -------------
    // A barreira de ORIGEM das duas. A regra inteira e a justificativa das
    // decisões estão em App\Core\Security\AdminIpAllowlist; aqui ficam apenas
    // os valores que a operação ajusta.
    'admin' => [
        // Origens permitidas no /admin e no /horizon (ADR-011, checklist item
        // 25). Lista separada por vírgula em ADMIN_ALLOWED_IPS, aceitando IP
        // exato, faixa CIDR IPv4 (`198.51.100.0/24`) e IPv6 com ou sem prefixo
        // (`2001:db8::1`, `2001:db8::/32`). Espaços em volta de cada item são
        // aparados: `10.0.0.1, 10.0.0.2` é como uma pessoa escreve uma lista, e
        // antes o segundo valor chegava com espaço e era rejeitado em silêncio.
        //
        // EM PRODUÇÃO, LISTA VAZIA NÃO SIGNIFICA MAIS "SEM RESTRIÇÃO": o
        // /admin e o /horizon RECUSAM (403) enquanto a origem permitida for
        // desconhecida. Antes, vazia liberava geral — e vazia era o padrão do
        // docker-compose.prod.yml, então a barreira que este arquivo prometia
        // não existia em nenhuma instalação que não a tivesse preenchido à mão.
        // Fora de produção, vazia continua liberando (conveniência de
        // desenvolvimento: o IP de quem desenvolve é o que o Docker der).
        'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', '')))),

        // ESCAPE HATCH da linha acima: assume a ausência de allowlist NA
        // APLICAÇÃO como decisão declarada, em vez de recusa. Legítimo para
        // quem administra de IP dinâmico e para quem já tem a segunda barreira
        // FORA da aplicação (rede só por VPN, Cloudflare Access, WAF com regra
        // de origem). Não tem valor padrão verdadeiro, não aparece
        // descomentado em nenhum arquivo de exemplo, e enquanto estiver valendo
        // o AppServiceProvider grava aviso no log a cada boot — opt-out de
        // segurança que ninguém vê volta a ser esquecimento. Quando a lista
        // acima tem conteúdo, ela VENCE: esta variável responde só "o que
        // significa uma lista vazia em produção".
        'allow_any_ip' => (bool) env('ADMIN_ALLOW_ANY_IP', false),
    ],

    // --- Delegação de detecção de ataques (vitrine de segurança do /ui) ------
    // Caminhos/componentes cuja detecção é feita pela PRÓPRIA camada da
    // aplicação, em vez do bloqueio 422 do middleware SecurityValidation.
    // A camada delegada roda o MESMO AttackDetector e registra a tentativa
    // (form_submissions com blocked_at + payload inerte — vitrine exibida
    // no super admin). NUNCA adicionar rotas de produção aqui sem
    // implementar a detecção local correspondente.
    'validation' => [
        // POST clássico do form demo (Blade).
        'delegated_paths' => array_filter(explode(',', (string) env('SECURITY_VALIDATION_DELEGATED_PATHS', 'ui/form-demo'))),

        // Componentes Livewire "autodefendidos": quando TODOS os componentes
        // de um /livewire/update estão nesta lista, a detecção é delegada a
        // eles (o form demo Livewire roda o AttackDetector no send()).
        'delegated_components' => array_filter(explode(',', (string) env('SECURITY_VALIDATION_DELEGATED_COMPONENTS', 'contact-form'))),
    ],

    // --- Pipeline de logs de requisição (ADR-004) ------------------------------
    'request_logging' => [
        // Rotas excluídas do log pesado em banco: health checks barulhentos e
        // assets estáticos (em produção o nginx serve direto; em dev local o
        // servidor embutido as deixa chegar ao Laravel). Continuam passando
        // pela validação de segurança, headers e rate limit, e ficam no access
        // log do nginx. Preflights OPTIONS também são excluídos (middleware).
        'excluded_paths' => array_filter(explode(',', (string) env('REQUEST_LOG_EXCLUDED_PATHS', 'up,api/health,favicon.ico,build/*,storage/*,vendor/*'))),

        // Rotas registradas com payload RESUMIDO: os updates genéricos do
        // Livewire (/livewire/update — usado pelo painel e pelo /admin)
        // carregam snapshots serializados enormes e repetitivos; o log guarda
        // apenas os nomes dos componentes envolvidos. Continuam auditadas
        // (método, endpoint, duração, status), só sem o payload bruto.
        'summarized_paths' => array_filter(explode(',', (string) env('REQUEST_LOG_SUMMARIZED_PATHS', 'livewire/*,livewire-*,admin/livewire/*'))),

        // Marcador gravado na coluna `endpoint` quando NENHUMA rota casa com
        // a requisição (404, varredura, método não permitido). O caminho real
        // NUNCA é gravado: ele é dado do usuário e pode carregar segredo
        // posicional (ver App\Core\Logging\EndpointSignature). O marcador
        // recebe a profundidade do caminho pedido (ex.: `[unmatched]:3`), que
        // separa sondagem de raiz de traversal profundo sem revelar conteúdo.
        'unmatched_endpoint' => (string) env('REQUEST_LOG_UNMATCHED_ENDPOINT', '[unmatched]'),

        // Tamanho máximo da correlação informada pelo CLIENTE (header de
        // entrada X-Correlation-Id), gravada em `client_correlation_id`. É
        // dado hostil: além deste limite, o valor é cortado, e fora da lista
        // branca de caracteres nada sobrevive (ver
        // App\Core\Logging\CorrelationId). O teto da coluna é 255 — valores
        // maiores na configuração são limitados a ele.
        'client_correlation_max_length' => (int) env('REQUEST_LOG_CLIENT_CORRELATION_MAX_LENGTH', 128),
    ],

];
