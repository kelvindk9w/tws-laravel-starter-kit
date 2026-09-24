<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\ProtectedAccounts;

/**
 * CONTAS PROTEGIDAS no nível do model (ponto de extensão
 * Twstec\Kit\Auth\Contracts\AccountProtection).
 *
 * A interface do super admin já recusa, mas ela só protege de quem clica.
 * Estes eventos protegem também de quem digita: tinker, comando artisan, job,
 * rotina de importação — tudo que passa por Eloquent. Quais contas, quais
 * campos e com que mensagem é decisão da extensão registrada; sem nenhuma, os
 * eventos não recusam nada.
 */
trait ProtectsAccounts
{
    public static function bootProtectsAccounts(): void
    {
        static::updating(function (AuthUser $user): void {
            ProtectedAccounts::guardUpdate($user);
        });

        static::deleting(function (AuthUser $user): void {
            ProtectedAccounts::guardDelete($user);
        });

        // `forceDeleting` só existe com SoftDeletes; registrado aqui para
        // que a proteção continue de pé no dia em que o model adotar exclusão
        // lógica (o evento é ignorado enquanto não houver).
        static::registerModelEvent('forceDeleting', function (AuthUser $user): void {
            ProtectedAccounts::guardDelete($user);
        });
    }

    /**
     * Conta reservada por uma extensão de proteção?
     *
     * Conta reservada NÃO pode ser bloqueada, editada, excluída nem ter o
     * e-mail verificado por ações do super admin — as telas verificam esta
     * guarda e avisam. Os eventos do model recusam o mesmo por qualquer outro
     * caminho enquanto a proteção vale. Sem extensão registrada, nenhuma conta
     * é reservada.
     */
    public function isReservedAccount(): bool
    {
        return ProtectedAccounts::reserves($this);
    }
}
