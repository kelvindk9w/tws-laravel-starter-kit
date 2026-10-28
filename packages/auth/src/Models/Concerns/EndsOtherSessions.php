<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Illuminate\Support\Str;
use Twstec\Kit\Auth\Contracts\AuthUser;
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
 * - a sessão de QUEM fez a mudança acompanha a mudança e continua aberta.
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
            if ($user instanceof AuthUser && $user->wasChanged(SessionFingerprint::ATTRIBUTES)) {
                SessionFingerprint::refreshCurrent($user);
            }
        });
    }
}
