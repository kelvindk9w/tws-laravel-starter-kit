<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Support;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;

/**
 * Guardas do CRUD de usuários do /admin (ADR-011).
 *
 * Regras — todas verificadas no SERVIDOR, não só escondendo botão:
 * 1. Conta demo é intocável (um visitante não quebra a demo dos outros);
 * 2. o admin não se exclui nem se bloqueia (não existe "me tranquei fora");
 * 3. o último admin ATIVO não perde a flag, não é bloqueado e não é
 *    excluído — o painel ficaria sem dono e só o comando `user:make-admin`
 *    (que exige shell no servidor) recuperaria o acesso.
 *
 * Cada método devolve NULL quando a ação é permitida ou a mensagem
 * traduzida do motivo quando não é.
 */
final class UserAdminGuard
{
    /**
     * Pode editar este registro?
     */
    public static function editDenial(User $record): ?string
    {
        return $record->isDemo() ? __('admin.users.demo_protected') : null;
    }

    /**
     * Pode excluir este registro?
     */
    public static function deleteDenial(User $record, ?User $actor): ?string
    {
        if ($record->isDemo()) {
            return __('admin.users.demo_protected');
        }

        if ($actor !== null && $actor->getKey() === $record->getKey()) {
            return __('admin.users.cannot_delete_self');
        }

        if (self::isLastActiveAdmin($record)) {
            return __('admin.users.cannot_remove_last_admin');
        }

        return null;
    }

    /**
     * Pode bloquear este registro?
     */
    public static function blockDenial(User $record, ?User $actor): ?string
    {
        if ($record->isDemo()) {
            return __('admin.users.demo_protected');
        }

        if ($actor !== null && $actor->getKey() === $record->getKey()) {
            return __('admin.users.cannot_block_self');
        }

        if (self::isLastActiveAdmin($record)) {
            return __('admin.users.cannot_remove_last_admin');
        }

        return null;
    }

    /**
     * A alteração pretendida (status/is_admin) mantém o painel com dono?
     *
     * @param  array<string, mixed>  $data
     */
    public static function updateDenial(User $record, array $data, ?User $actor): ?string
    {
        if ($record->isDemo()) {
            return __('admin.users.demo_protected');
        }

        $viraNaoAdmin = array_key_exists('is_admin', $data) && ! $data['is_admin'];
        $viraInativo = array_key_exists('status', $data)
            && UserStatus::from((string) $data['status']) !== UserStatus::Active;

        if (($viraNaoAdmin || $viraInativo) && self::isLastActiveAdmin($record)) {
            return __('admin.users.cannot_remove_last_admin');
        }

        if ($viraInativo && $actor !== null && $actor->getKey() === $record->getKey()) {
            return __('admin.users.cannot_block_self');
        }

        return null;
    }

    /**
     * Este é o ÚLTIMO admin ativo do sistema?
     */
    public static function isLastActiveAdmin(User $record): bool
    {
        if (! $record->is_admin || ! $record->isActive()) {
            return false;
        }

        return User::query()
            ->where('is_admin', true)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($record->getKey())
            ->doesntExist();
    }
}
