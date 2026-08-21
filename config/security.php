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

        // HSTS: só enviado sob HTTPS e quando habilitado (padrão: produção).
        'hsts_enabled' => env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
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

    // --- Super admin (/admin — Filament) ---------------------------------------
    'admin' => [
        // IP allowlist do painel super admin (ADR-011: obrigatória desde o
        // go-live em produção — checklist item 25). Lista de IPs separados
        // por vírgula no .env (ADMIN_ALLOWED_IPS); VAZIO = sem restrição
        // (apenas para desenvolvimento). Middleware: EnsureAdminIpAllowed.
        'allowed_ips' => array_filter(explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))),
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
        'summarized_paths' => array_filter(explode(',', (string) env('REQUEST_LOG_SUMMARIZED_PATHS', 'livewire/*,admin/livewire/*'))),
    ],

];
