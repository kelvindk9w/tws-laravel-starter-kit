<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Sessions;

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Events\Dispatcher;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\SessionEndReason;
use Twstec\Kit\Auth\Events\SessionsEnded;

/**
 * Traduz os encerramentos de sessão do framework em Events\SessionsEnded —
 * ligado pelo AuthServiceProvider, sempre (não depende das proteções web).
 *
 * - `Logout` / `CurrentDeviceLogout`: o ouvinte roda DENTRO do logout, antes
 *   do `invalidate()` que apaga os dados da sessão — o vínculo ainda está lá;
 * - `OtherDeviceLogout`: todas menos a sessão atual.
 *
 * A troca de credencial e a desativação da conta disparam pelo model (trait
 * Models\Concerns\EndsOtherSessions).
 */
final class SessionEndEvents
{
    public static function register(Dispatcher $events): void
    {
        $events->listen(Logout::class, static function (Logout $event): void {
            self::currentSessionEnded($event->user);
        });

        $events->listen(CurrentDeviceLogout::class, static function (CurrentDeviceLogout $event): void {
            self::currentSessionEnded($event->user);
        });

        $events->listen(OtherDeviceLogout::class, static function (OtherDeviceLogout $event): void {
            if (! $event->user instanceof AuthUser) {
                return;
            }

            $session = SessionBinding::currentSession();
            $kept = $session !== null ? SessionBinding::current($session, $event->user) : null;

            event(SessionsEnded::allExcept($event->user, $kept, SessionEndReason::OtherDevices));
        });
    }

    private static function currentSessionEnded(mixed $user): void
    {
        if (! $user instanceof AuthUser) {
            return;
        }

        $session = SessionBinding::currentSession();
        $ref = $session !== null ? SessionBinding::current($session, $user) : null;

        event(SessionsEnded::session($user, $ref, SessionEndReason::Logout));
    }
}
