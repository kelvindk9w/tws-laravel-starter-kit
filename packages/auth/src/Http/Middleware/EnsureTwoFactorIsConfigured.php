<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Auth\Support\TwoFactorRequirement;

/**
 * Segundo fator OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED): quem ainda não ligou
 * configura ANTES de qualquer outra tela.
 *
 * ONDE RODA: anexado pelo pacote ao FIM do grupo `web` (depois do
 * EnsureAccountIsActive) — toda rota do grupo passa por aqui, inclusive as
 * que ainda vão existir e o endpoint de atualização do Livewire. É uma lista
 * do que FICA ABERTO (`auth.two_factor.setup_allowed_routes`: a própria tela
 * de configuração e os envios dela, sair, idioma, tema, definir a senha de
 * transação), não do que fecha: rota nova nasce fechada para quem está
 * pendente. Uma ação Livewire de qualquer página é recusada aqui mesmo,
 * porque a rota do endpoint do Livewire não está na lista — por isso a tela
 * de configuração dos starters é formulário comum, não componente.
 *
 * QUANDO BARRA: conta autenticada no guard `web`, com a regra valendo, sem o
 * segundo fator e sem carência (TwoFactorRequirement::mustSetUpNow). Conta
 * com e-mail ainda por confirmar passa adiante: a confirmação vem primeiro
 * (o `verified` a leva ao aviso) — e o código do segundo fator vai para esse
 * mesmo e-mail.
 *
 * COMO RESPONDE: navegação vai à tela `two-factor.setup` (guardando o destino
 * de um GET de página, que o fim da configuração devolve pelo SafeRedirect);
 * chamada que espera JSON recebe 403 com a mensagem traduzida. Sem a rota de
 * configuração no front, 403 — nunca deixa passar.
 *
 * O QUE NÃO COBRE: o /admin tem pilha própria e exige o segundo fator pelo
 * mecanismo de MFA do Filament (twstec/kit-admin); a API pública não usa
 * sessão (chave pk_/sk_).
 */
final class EnsureTwoFactorIsConfigured
{
    public const SETUP_ROUTE = 'two-factor.setup';

    public function __construct(
        private readonly TwoFactorRequirement $requirement,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof AuthUser
            || ! $this->requirement->mustSetUpNow($user)
            || EmailVerification::pendingFor($user)
            || $this->allowed($request)) {
            return $next($request);
        }

        $message = __('auth.two_factor.setup_required');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort_unless(Route::has(self::SETUP_ROUTE), Response::HTTP_FORBIDDEN, $message);

        // Guarda o destino só em navegação de verdade (GET de página): ação
        // Livewire e envio de formulário não são destino.
        if ($request->isMethod('GET') && ! $request->hasHeader('X-Livewire')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route(self::SETUP_ROUTE);
    }

    private function allowed(Request $request): bool
    {
        $patterns = array_values(array_filter(
            (array) config('auth.two_factor.setup_allowed_routes', []),
            static fn (mixed $pattern): bool => is_string($pattern) && $pattern !== '',
        ));

        return $patterns !== [] && $request->route() !== null && $request->routeIs(...$patterns);
    }
}
