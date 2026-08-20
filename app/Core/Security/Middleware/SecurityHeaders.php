<?php

declare(strict_types=1);

namespace App\Core\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers HTTP de segurança (OWASP Secure Headers — checklist item 20).
 *
 * Aplicados na camada da aplicação (defesa em profundidade: o nginx de
 * produção também envia os principais). Valores via config/security.php,
 * ajustáveis por .env.
 *
 * HSTS só é enviado sob HTTPS e quando habilitado (padrão: produção) —
 * enviar HSTS em HTTP é inócuo, mas habilitar em dev atrapalha.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', (string) config('security.headers.frame_options', 'DENY'));
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));

        $csp = config('security.headers.content_security_policy');

        if (is_string($csp) && $csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        if (config('security.headers.hsts_enabled', false) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
