<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\EmailVerification;

/**
 * Painel só para conta com e-mail confirmado (alias `verified`).
 *
 * Por que não o `EnsureEmailIsVerified` do framework: ele não conhece a flag
 * AUTH_EMAIL_VERIFICATION_REQUIRED, responde em inglês fixo e não está na
 * lista de middleware PERSISTENTE do Livewire — ou seja, uma ação de
 * componente (criar chave de API, criar projeto) disparada pelo endpoint de
 * atualização do Livewire passaria sem ele. Este é registrado como persistente
 * no AppServiceProvider: a ação Livewire de uma página `verified` passa por
 * aqui com a rota original, exatamente como a página.
 *
 * A pergunta "esta conta está barrada?" é do EmailVerification (a mesma que a
 * API faz no ResolveTenant). Conta barrada vai à tela de aviso; chamada que
 * espera JSON recebe 403 com a mensagem traduzida.
 */
final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AuthUser || ! EmailVerification::pendingFor($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.email_verification.not_verified')], Response::HTTP_FORBIDDEN);
        }

        // Guarda o destino só em navegação de verdade (GET de página): depois
        // de confirmar, a pessoa volta para onde queria ir — pelo SafeRedirect,
        // nunca por `intended()` cru. Ação Livewire não é destino de navegação.
        if ($request->isMethod('GET') && ! $request->hasHeader('X-Livewire')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('verification.notice');
    }
}
