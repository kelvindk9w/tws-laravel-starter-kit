<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\ConfirmSensitiveCodeRequest;
use App\Core\Auth\Http\Requests\RequestSensitiveCodeRequest;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\SensitiveActionService;
use Illuminate\Http\JsonResponse;

/**
 * Fluxo de confirmação de ação sensível (ADR-006/010):
 *
 *   POST /sensitive-actions/code    — senha de transação → código por e-mail
 *   POST /sensitive-actions/confirm — código válido → token de ação sensível
 *
 * O token retornado autoriza UMA operação sensível (uso único, curta duração)
 * em rotas protegidas pelo middleware `sensitive.token`.
 *
 * Respostas JSON: os formulários do painel (fase Livewire) consomem estes
 * endpoints via fetch com o token CSRF da sessão (checklist 23).
 */
final class SensitiveActionController
{
    public function __construct(
        private readonly SensitiveActionService $sensitiveActions,
    ) {}

    public function store(RequestSensitiveCodeRequest $request): JsonResponse
    {
        /** @var User $user */
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
        /** @var User $user */
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
