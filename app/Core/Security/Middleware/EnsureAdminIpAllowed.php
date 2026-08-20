<?php

declare(strict_types=1);

namespace App\Core\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP allowlist do super admin /admin (ADR-011 — checklist item 25).
 *
 * A lista vem de config/security.php (ADMIN_ALLOWED_IPS). Vazia = sem
 * restrição (apenas desenvolvimento); em produção a allowlist é obrigatória
 * por política do projeto. IPs por CIDR são suportados via IpUtils do
 * Symfony (Request::ip() já resolve proxies confiáveis).
 */
final class EnsureAdminIpAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $allowed */
        $allowed = array_values((array) config('security.admin.allowed_ips', []));

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
