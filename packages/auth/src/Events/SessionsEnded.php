<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Events;

use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\SessionEndReason;

/**
 * Uma ou mais sessões web de uma pessoa TERMINARAM — o gancho para o que
 * vive preso a uma sessão (a chave de API de sessão do twstec/kit-accounts,
 * dado do aplicativo guardado por sessão) morrer junto, na hora.
 *
 * Quem dispara (o pacote, sozinho):
 * - logout e encerramento do aparelho atual (eventos `Logout` e
 *   `CurrentDeviceLogout` do framework, guard web) → só a sessão desta
 *   requisição (`session()`), motivo `Logout`;
 * - "sair dos outros aparelhos" (`OtherDeviceLogout`, de
 *   `Auth::logoutOtherDevices`) → todas menos a atual, `OtherDevices`;
 * - troca ou redefinição da senha de login e ligar/desligar o segundo fator
 *   (o model, trait EndsOtherSessions) → todas menos a de quem mudou, se ela
 *   está nesta requisição; na redefinição por e-mail, todas;
 *   `CredentialsChanged`;
 * - a conta deixa de estar ativa (bloqueio pelo /admin, por exemplo) →
 *   todas, `AccountDeactivated`.
 *
 * O aplicativo que encerra sessão por um caminho próprio (uma tela "derrubar
 * sessões", apagar linhas da tabela `sessions`) dispara o evento com
 * `event(SessionsEnded::allExcept($pessoa, null, SessionEndReason::Administrative))`.
 *
 * As sessões são identificadas pela REFERÊNCIA do vínculo
 * (Sessions\SessionBinding), nunca pelo id da sessão. A sessão que nunca
 * ligou nada não tem referência: `session()` com referência nula não encerra
 * nenhum recurso ligado. Use ends() para saber se uma referência terminou.
 *
 * Disparado de forma síncrona e, quando a mudança está numa transação, dentro
 * dela: o que o ouvinte grava no banco é desfeito junto se a mudança for.
 */
final class SessionsEnded
{
    private function __construct(
        public readonly AuthUser $user,
        public readonly SessionEndReason $reason,
        public readonly bool $allSessions,
        public readonly ?string $sessionRef,
        public readonly ?string $keptSessionRef,
    ) {}

    /**
     * Terminou UMA sessão (a de referência `$sessionRef`; nula = uma sessão
     * sem nada ligado).
     */
    public static function session(AuthUser $user, ?string $sessionRef, SessionEndReason $reason): self
    {
        return new self($user, $reason, false, $sessionRef, null);
    }

    /**
     * Terminaram TODAS as sessões da pessoa, menos a de referência
     * `$keptSessionRef` (nula = todas).
     */
    public static function allExcept(AuthUser $user, ?string $keptSessionRef, SessionEndReason $reason): self
    {
        return new self($user, $reason, true, null, $keptSessionRef);
    }

    /**
     * A sessão de vínculo `$sessionRef` terminou com este evento?
     */
    public function ends(string $sessionRef): bool
    {
        if ($this->allSessions) {
            return $this->keptSessionRef === null || ! hash_equals($this->keptSessionRef, $sessionRef);
        }

        return $this->sessionRef !== null && hash_equals($this->sessionRef, $sessionRef);
    }
}
