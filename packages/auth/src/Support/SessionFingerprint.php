<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use DateTimeInterface;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * "Com que credenciais esta sessão foi aberta" — a impressão digital que
 * encerra as OUTRAS sessões quando a senha de login ou o segundo fator da
 * conta mudam.
 *
 * A sessão guarda, ao lado do id da conta, um HMAC (chave da aplicação) do
 * hash da senha de login e do momento em que o segundo fator foi ligado. A
 * cada requisição web o EnsureSessionIsCurrent compara com o estado atual da
 * conta: mudou → a sessão acabou. Nada depende do driver de sessão (redis,
 * banco, arquivo): não é preciso achar as sessões da conta para apagá-las, a
 * próxima requisição de cada uma se encerra sozinha.
 *
 * Quem mudou a credencial NÃO cai: quando a conta salva é a autenticada na
 * requisição atual, a impressão desta sessão é atualizada na hora
 * (refreshCurrent, chamado pelo model — trait EndsOtherSessions).
 */
final class SessionFingerprint
{
    public const SESSION_KEY = 'kit_auth.session_fingerprint';

    /**
     * Atributos do model cuja mudança encerra as outras sessões.
     *
     * @var list<string>
     */
    public const ATTRIBUTES = ['password', 'two_factor_enabled_at'];

    /**
     * Guard de sessão do painel (o mesmo do EnsureAccountIsActive).
     */
    private const GUARD = 'web';

    public static function of(AuthUser $user): string
    {
        $twoFactor = $user->two_factor_enabled_at;

        $material = $user->getAuthPassword()
            .'|'.($twoFactor instanceof DateTimeInterface ? (string) $twoFactor->getTimestamp() : '');

        return hash_hmac('sha256', $material, (string) config('app.key'));
    }

    /**
     * A sessão guarda a impressão DESTA conta? (false também para a de
     * outra conta — sessão reaproveitada — ou sessão anterior à proteção).
     */
    public static function isStoredFor(Session $session, AuthUser $user): bool
    {
        $stored = $session->get(self::SESSION_KEY);

        return is_array($stored)
            && ($stored['user'] ?? null) === (string) $user->getAuthIdentifier()
            && is_string($stored['hash'] ?? null);
    }

    /**
     * A impressão guardada para esta conta é diferente da atual? Sem impressão
     * guardada, não está vencida (ainda não há com o que comparar).
     */
    public static function isStale(Session $session, AuthUser $user): bool
    {
        if (! self::isStoredFor($session, $user)) {
            return false;
        }

        /** @var array{user: string, hash: string} $stored */
        $stored = $session->get(self::SESSION_KEY);

        return ! hash_equals(self::of($user), $stored['hash']);
    }

    public static function store(Session $session, AuthUser $user): void
    {
        $session->put(self::SESSION_KEY, [
            'user' => (string) $user->getAuthIdentifier(),
            'hash' => self::of($user),
        ]);
    }

    /**
     * A conta salva é a autenticada nesta requisição? Então esta sessão
     * acompanha a mudança e continua aberta — só as outras caem.
     *
     * Também atualiza o hash de senha que o AuthenticateSession do framework
     * (o do /admin, pelo Filament) guarda na mesma sessão: sem isso, quem
     * trocou a senha no painel cairia ao abrir o /admin.
     */
    public static function refreshCurrent(AuthUser $user): void
    {
        $session = self::currentSession();

        if ($session === null) {
            return;
        }

        $guard = Auth::guard(self::GUARD);

        if (! $guard->hasUser() || (string) $guard->id() !== (string) $user->getAuthIdentifier()) {
            return;
        }

        self::store($session, $user);

        $frameworkKey = 'password_hash_'.self::GUARD;

        if ($guard instanceof SessionGuard && $session->has($frameworkKey)) {
            $session->put($frameworkKey, $guard->hashPasswordForCookie($user->getAuthPassword()));
        }
    }

    /**
     * A sessão desta execução: a da requisição ou, quando quem salva roda
     * fora da pilha HTTP (ex.: o componente Livewire nos testes, que dispensa
     * os middlewares), a do gerenciador. Quem decide se há "sessão de quem
     * mudou" é o guard (refreshCurrent): sem conta autenticada nesta
     * execução — fila, console —, nada é gravado.
     */
    private static function currentSession(): ?Session
    {
        if (app()->bound('request') && request()->hasSession()) {
            return request()->session();
        }

        if (! app()->bound('session.store')) {
            return null;
        }

        $session = app('session.store');

        return $session instanceof Session ? $session : null;
    }
}
