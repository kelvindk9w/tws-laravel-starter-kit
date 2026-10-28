<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\SessionFingerprint;

/**
 * Sessão aberta com uma senha (ou um estado do segundo fator) que já não vale
 * mais é encerrada na requisição seguinte — página, formulário ou ação
 * Livewire (o endpoint de atualização do Livewire está no grupo `web`).
 *
 * É o que faz "trocar a senha derruba quem estava usando a conta" ser
 * verdade: depois de uma redefinição por e-mail, de uma troca no perfil ou de
 * ligar/desligar o segundo fator, a impressão guardada na sessão
 * (Support\SessionFingerprint) deixa de bater com a conta. A sessão de quem fez
 * a mudança é atualizada no ato (Models\Concerns\EndsOtherSessions) e segue
 * aberta. Funciona com qualquer driver de sessão: cada sessão se confere
 * sozinha, ninguém precisa listá-las.
 *
 * Sessão sem impressão (aberta antes desta proteção, ou que acabou de
 * autenticar) ganha a impressão do estado atual da conta.
 *
 * O encerramento é o do aparelho atual (logoutCurrentDevice): não renova o
 * `remember_token` da conta — a mudança de credencial já o renovou.
 */
final class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        $user = $guard->user();

        if ($user instanceof AuthUser) {
            if (SessionFingerprint::isStale($request->session(), $user)) {
                return $this->endSession($request);
            }

            if (! SessionFingerprint::isStoredFor($request->session(), $user)) {
                SessionFingerprint::store($request->session(), $user);
            }
        }

        $response = $next($request);

        // Autenticou nesta requisição (login, segundo fator, cadastro): a
        // sessão nova já nasce com a impressão.
        $current = $guard->user();

        if ($current instanceof AuthUser && ! SessionFingerprint::isStoredFor($request->session(), $current)) {
            SessionFingerprint::store($request->session(), $current);
        }

        return $response;
    }

    private function endSession(Request $request): Response
    {
        $message = __('auth.session_ended');

        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        } elseif ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
