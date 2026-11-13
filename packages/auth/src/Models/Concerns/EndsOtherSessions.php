<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Illuminate\Support\Str;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\SessionEndReason;
use Twstec\Kit\Auth\Events\SessionsEnded;
use Twstec\Kit\Auth\Sessions\SessionBinding;
use Twstec\Kit\Auth\Support\SessionFingerprint;

/**
 * Trocar a senha de login ou ligar/desligar o segundo fator ENCERRA AS OUTRAS
 * SESSÕES da conta — por qualquer caminho (redefinição por e-mail, perfil do
 * painel, /admin, comando, seeder), porque a regra mora no model, não na tela.
 *
 * - `remember_token` renovado ao salvar: nenhum cookie "lembrar de mim"
 *   emitido antes volta a abrir sessão (inclusive o deste aparelho, que segue
 *   logado pela sessão atual);
 * - as sessões abertas caem na próxima requisição web
 *   (Http\Middleware\EnsureSessionIsCurrent compara a impressão
 *   Support\SessionFingerprint);
 * - a sessão de QUEM fez a mudança acompanha a mudança e continua aberta;
 * - o evento Events\SessionsEnded sai NA HORA (motivo `CredentialsChanged`,
 *   todas menos a de quem mudou), para o que está preso a uma sessão morrer
 *   sem esperar a próxima requisição dela.
 *
 * A conta que deixa de estar ATIVA (bloqueada ou pendente — o bloqueio do
 * /admin, por exemplo) também tem todas as sessões encerradas na próxima
 * requisição (EnsureAccountIsActive); o evento sai na hora, com o motivo
 * `AccountDeactivated`, para todas.
 */
trait EndsOtherSessions
{
    public static function bootEndsOtherSessions(): void
    {
        static::saving(static function (self $user): void {
            if ($user->exists && $user->isDirty(SessionFingerprint::ATTRIBUTES)) {
                $user->setRememberToken(Str::random(60));
            }
        });

        static::saved(static function (self $user): void {
            if (! $user instanceof AuthUser) {
                return;
            }

            if ($user->wasChanged(SessionFingerprint::ATTRIBUTES)) {
                SessionFingerprint::refreshCurrent($user);

                event(SessionsEnded::allExcept($user, SessionBinding::ofCurrentRequestFor($user), SessionEndReason::CredentialsChanged));
            }

            if ($user->wasChanged('status') && ! $user->isActive()) {
                event(SessionsEnded::allExcept($user, null, SessionEndReason::AccountDeactivated));
            }
        });
    }
}
