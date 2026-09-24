<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use Illuminate\Http\Request;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * O ESTADO INTERMEDIÁRIO do login com segundo fator (painel do cliente).
 *
 * Senha certa numa conta com a verificação em duas etapas ligada NÃO
 * autentica: o login grava aqui, na sessão (do lado do servidor), só o
 * necessário para concluir depois — qual conta, se "manter conectado" foi
 * marcado, até quando o estado vale e uma impressão digital da senha. O guard
 * continua vazio (`Auth::check()` é falso): nenhuma página, formulário ou ação
 * Livewire do painel enxerga essa sessão como logada.
 *
 * O estado deixa de valer quando:
 *   - passa da validade (AUTH_TWO_FACTOR_CHALLENGE_TTL_MINUTES) — a pessoa
 *     volta ao login e digita a senha de novo;
 *   - a senha da conta muda no meio do caminho (redefinição por e-mail,
 *     troca no perfil por outra sessão): a impressão digital não bate mais;
 *   - a conta some.
 *
 * "Manter conectado" fica guardado aqui e só é aplicado DEPOIS do código
 * certo: o cookie de longa duração nunca nasce de meio login.
 */
final class PendingTwoFactorLogin
{
    public const SESSION_KEY = 'auth.two_factor_login';

    /**
     * Abre o estado intermediário para a conta.
     */
    public static function start(Request $request, AuthUser $user, bool $remember, int $ttlMinutes): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'expires_at' => now()->addMinutes($ttlMinutes)->getTimestamp(),
            'fingerprint' => self::fingerprint($user),
        ]);
    }

    /**
     * Há um estado intermediário gravado (vivo ou não)?
     */
    public static function exists(Request $request): bool
    {
        return is_array($request->session()->get(self::SESSION_KEY));
    }

    /**
     * A conta do estado intermediário, se ele ainda vale. Estado vencido,
     * de conta que sumiu ou cuja senha mudou devolve null (e quem chama
     * decide o que dizer — ver `forget`).
     */
    public static function user(Request $request): ?AuthUser
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state) || ! isset($state['user_id'], $state['expires_at'], $state['fingerprint'])) {
            return null;
        }

        if ((int) $state['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        /** @var AuthUser|null $user */
        $user = UserModel::query()->find($state['user_id']);

        if ($user === null || ! hash_equals((string) $state['fingerprint'], self::fingerprint($user))) {
            return null;
        }

        return $user;
    }

    /**
     * "Manter conectado" foi marcado no primeiro passo?
     */
    public static function remember(Request $request): bool
    {
        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) && ($state['remember'] ?? false) === true;
    }

    /**
     * Segundos de vida que restam ao estado (0 = vencido ou inexistente).
     */
    public static function secondsLeft(Request $request): int
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state) || ! isset($state['expires_at'])) {
            return 0;
        }

        return max(0, (int) $state['expires_at'] - now()->getTimestamp());
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Impressão digital da senha: HMAC do hash com a chave da aplicação. Muda
     * sempre que a senha muda; não expõe o hash na sessão.
     */
    private static function fingerprint(AuthUser $user): string
    {
        return hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key'));
    }
}
