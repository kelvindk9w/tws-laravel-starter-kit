<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Middleware;

use App\Core\Auth\Services\SensitiveActionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um token de ação sensível válido (ADR-006) para prosseguir.
 *
 * Uso em rotas de operações sensíveis (saque, criação/rotação de chave de
 * API, alterações críticas), SEMPRE combinado com `auth`:
 *
 *   Route::post('/saque', ...)->middleware(['auth', 'sensitive.token']);
 *
 * O token é lido do header `X-Sensitive-Action-Token` (preferido) ou do
 * campo `sensitive_action_token`. É de USO ÚNICO: a validação o consome.
 */
final class RequiresSensitiveActionToken
{
    public const HEADER = 'X-Sensitive-Action-Token';

    public function __construct(
        private readonly SensitiveActionService $sensitiveActions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $plainToken = $request->header(self::HEADER) ?? $request->input('sensitive_action_token');

        if ($user === null || ! is_string($plainToken) || ! $this->sensitiveActions->validateToken($user, $plainToken)) {
            abort(403, __('auth.sensitive_action.invalid_token'));
        }

        return $next($request);
    }
}
