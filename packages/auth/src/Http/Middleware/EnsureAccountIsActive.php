<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Status da conta vale A CADA REQUISIÇÃO web, não só no login (deny-by-default).
 *
 * O login já recusava conta não ativa, mas era a única verificação do lado
 * web: uma conta bloqueada DEPOIS de logada mantinha a sessão e seguia
 * operando o painel (páginas, formulários e ações Livewire) até a sessão
 * expirar. Aqui, conta autenticada que não está ATIVA tem a sessão encerrada
 * na requisição seguinte ao bloqueio e vai ao login com a mesma mensagem
 * traduzida que o próprio login daria.
 *
 * ONDE RODA: no grupo `web`, depois do SetLocale (a mensagem sai no idioma da
 * conta). O endpoint de atualização do Livewire também está no grupo `web`,
 * então a ação de componente cai aqui antes de o componente ser hidratado —
 * sem depender de cada componente conferir o status. Rotas públicas também
 * passam por aqui: uma conta bloqueada que visita a landing perde a sessão
 * ali mesmo, que é o comportamento desejado (sessão de conta bloqueada não
 * deve sobreviver em lugar nenhum).
 *
 * O QUE NÃO COBRE, de propósito: o /admin tem pilha própria (Filament) e já
 * exige conta ativa no canAccessPanel; a API não usa sessão e o ResolveTenant
 * confere o dono da chave a cada chamada.
 *
 * PENDING = BLOCKED. Deny-by-default: só Active opera. "Pendente" é conta que
 * ainda não foi liberada; se uma conta volta a pendente com sessão aberta, ela
 * perde o acesso como uma bloqueada. É a mesma decisão que o login e a API já
 * tomavam (ambos usam User::isActive()).
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof AuthUser) {
            $this->keepNoticeAcrossLivewireRedirect($request);

            return $next($request);
        }

        if ($user->isActive()) {
            return $next($request);
        }

        $message = __('auth.account_inactive');

        // Mesmo encerramento do logout voluntário: sai do guard (o que também
        // descarta o cookie "lembrar de mim"), invalida a sessão e renova o
        // token CSRF — nada da sessão antiga sobrevive.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    /**
     * Quando a recusa acontece numa ação Livewire, o cliente JS do Livewire
     * SEGUE o redirecionamento por conta própria (fetch, com o mesmo header
     * X-Livewire) e só depois leva o navegador ao login. Esse GET intermediário
     * consumiria a mensagem da sessão antes de a pessoa ver a tela; aqui ela é
     * mantida por mais uma requisição. Só mexe em mensagem flash já existente —
     * não cria nada, não afeta requisição autenticada.
     */
    private function keepNoticeAcrossLivewireRedirect(Request $request): void
    {
        if ($request->isMethod('GET') && $request->hasHeader('X-Livewire') && $request->hasSession()) {
            $request->session()->reflash();
        }
    }
}
