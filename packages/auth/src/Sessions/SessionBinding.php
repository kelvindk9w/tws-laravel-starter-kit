<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Sessions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * O VÍNCULO de uma sessão web: o que liga um recurso de vida curta (a chave
 * de API de sessão do twstec/kit-accounts, por exemplo) à sessão em que ele
 * nasceu, para que ele morra quando ela terminar — por qualquer caminho.
 *
 * Por que não o id da sessão: o id muda (o login e outras etapas o
 * regeneram contra fixação), e guardá-lo no banco entregaria a sessão a quem
 * lesse a tabela. O vínculo é um valor ALEATÓRIO guardado DENTRO dos dados da
 * sessão (sobrevive à regeneração do id e some com o `invalidate()` do
 * logout), ligado à pessoa dona da sessão. Fora da sessão só circula a
 * REFERÊNCIA — o SHA-256 do valor —, que não abre nada.
 *
 * O vínculo só existe depois que alguém o pede (for()): sessão sem recurso
 * ligado não carrega nada.
 */
final class SessionBinding
{
    public const SESSION_KEY = 'kit_auth.session_binding';

    /**
     * Guard de sessão do painel (o mesmo do SessionFingerprint).
     */
    private const GUARD = 'web';

    /**
     * A referência do vínculo desta sessão para esta pessoa — criada na hora
     * se a sessão ainda não tem (ou se o vínculo guardado é de outra pessoa).
     */
    public static function for(Session $session, AuthUser $user): string
    {
        $current = self::current($session, $user);

        if ($current !== null) {
            return $current;
        }

        $token = Str::random(64);

        $session->put(self::SESSION_KEY, [
            'user' => (string) $user->getAuthIdentifier(),
            'token' => $token,
        ]);

        return self::reference($token);
    }

    /**
     * A referência do vínculo que a sessão já tem (null se não tem). Com
     * `$user`, só se o vínculo for dessa pessoa.
     */
    public static function current(Session $session, ?AuthUser $user = null): ?string
    {
        $stored = $session->get(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($stored['token'] ?? null) || $stored['token'] === '') {
            return null;
        }

        if ($user !== null && ($stored['user'] ?? null) !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return self::reference($stored['token']);
    }

    /**
     * A sessão desta requisição pertence a esta pessoa (é ela quem está
     * autenticada no guard web, com o id gravado na sessão)?
     */
    public static function belongsTo(Session $session, AuthUser $user): bool
    {
        // Outro tipo de guard no `web` (sem sessão) não tem dono de sessão.
        $guard = app(AuthFactory::class)->guard(self::GUARD);

        if (! $guard instanceof SessionGuard) {
            return false;
        }

        $stored = $session->get($guard->getName());

        return $stored !== null && (string) $stored === (string) $user->getAuthIdentifier();
    }

    /**
     * A referência do vínculo da sessão DESTA EXECUÇÃO, se quem está
     * autenticado nela é a pessoa informada (null em fila, console ou quando
     * a requisição é de outra pessoa — ex.: redefinição de senha sem login).
     */
    public static function ofCurrentRequestFor(AuthUser $user): ?string
    {
        $session = self::currentSession();

        if ($session === null) {
            return null;
        }

        $guard = Auth::guard(self::GUARD);

        if (! $guard->hasUser() || (string) $guard->id() !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return self::current($session, $user);
    }

    /**
     * A sessão desta execução: a da requisição ou a do gerenciador (mesma
     * regra do SessionFingerprint).
     */
    public static function currentSession(): ?Session
    {
        if (app()->bound('request') && request()->hasSession()) {
            return request()->session();
        }

        if (! app()->bound('session.store')) {
            return null;
        }

        /** @var Session */
        return app('session.store');
    }

    private static function reference(string $token): string
    {
        return hash('sha256', $token);
    }
}
