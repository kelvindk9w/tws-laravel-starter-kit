<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Http\Requests\ConfirmSensitiveCodeRequest;
use Twstec\Kit\Auth\Http\Requests\RequestSensitiveCodeRequest;
use Twstec\Kit\Auth\Services\SensitiveActionService;

/**
 * Fluxo de confirmação de ação sensível:
 *
 *   POST /sensitive-actions/code    — senha de transação → código por e-mail
 *   POST /sensitive-actions/confirm — código válido → token de ação sensível
 *
 * O token retornado autoriza UMA operação sensível (uso único, curta duração)
 * em rotas protegidas pelo middleware `sensitive.token`.
 *
 * Respostas JSON: endpoints para consumo via fetch com o token CSRF da
 * sessão (o painel Livewire chama o SensitiveActionService direto). O
 * `throttle:sensitive` vem com o controller (HasMiddleware).
 */
final class SensitiveActionController implements HasMiddleware
{
    public function __construct(
        private readonly SensitiveActionService $sensitiveActions,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive')];
    }

    public function store(RequestSensitiveCodeRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        $this->sensitiveActions->sendCode($user, $request->string('transaction_password')->toString());

        return response()->json([
            'message' => __('auth.verification_code.sent'),
            'expires_in_minutes' => (int) config('auth.verification.code_ttl_minutes', 10),
            'resend_available_in_seconds' => (int) config('auth.verification.resend_cooldown_seconds', 60),
        ]);
    }

    public function confirm(ConfirmSensitiveCodeRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        $issued = $this->sensitiveActions->confirmCode($user, $request->string('code')->toString());

        return response()->json([
            'message' => __('auth.sensitive_action.token_issued'),
            // O token em claro aparece UMA única vez — aqui (no banco, só hash).
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ]);
    }
}
