<?php

declare(strict_types=1);

namespace App\Core\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super admin (/admin — Filament 5): o Filament usa expressões Alpine
 * incompatíveis com o build CSP-safe do Alpine (sem eval) — modais e ações
 * não abrem. Para SOMENTE as páginas do /admin, o Livewire serve o bundle
 * JavaScript normal (livewire.js em vez de livewire.csp.min.js) e a CSP
 * dessas rotas ganha 'unsafe-eval' (SecurityHeaders + config/security.php
 * → content_security_policy_admin, decisão documentada).
 *
 * O painel do usuário e a API seguem com o bundle CSP-safe + CSP estrita.
 * Mitigação: /admin é painel interno — acesso por is_admin + IP allowlist
 * obrigatória em produção (ADR-011, checklist item 25).
 */
final class UseEvalBundleForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        config()->set('livewire.csp_safe', false);

        return $next($request);
    }
}
