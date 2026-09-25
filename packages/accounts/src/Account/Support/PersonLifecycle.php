<?php

declare(strict_types=1);

namespace Twstec\Kit\Accounts\Account\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\UserModel;
use WeakMap;

/**
 * O que acontece com as contas quando uma PESSOA nasce ou é excluída — ligado
 * pelo pacote aos eventos do model de usuário do aplicativo (o configurado em
 * `auth.providers.users.model`), sem o model precisar de trait nenhuma.
 *
 * - Criada: ganha a conta pessoal (mesmo uuid), como dona.
 * - Excluída:
 *   - recusada (exceção, nada muda) se ela é DONA de conta com outros
 *     membros — a propriedade precisa ser transferida antes;
 *   - senão, as contas de que ela era dona (a pessoal e as que só ela usava)
 *     saem com os dados — o mesmo efeito da 1.x; nas contas em que era admin
 *     ou member ela só deixa de ser membro, e as chaves que criou continuam
 *     valendo (são da conta).
 *
 * A recusa roda no `deleting` (antes de tocar no banco) e não tem efeito
 * colateral: se outra guarda recusar depois (conta protegida, por exemplo),
 * nada foi apagado. A limpeza roda no `deleted` (a pessoa já saiu); no
 * PostgreSQL os gatilhos das contas fazem o mesmo dentro da própria sentença.
 */
final class PersonLifecycle
{
    /**
     * Contas de que cada pessoa em exclusão era dona.
     *
     * @var WeakMap<AuthUser, list<int>>
     */
    private WeakMap $owned;

    public function __construct(private readonly AccountService $accounts)
    {
        $this->owned = new WeakMap;
    }

    public static function register(Dispatcher $events): void
    {
        $model = UserModel::name();

        $events->listen("eloquent.created: {$model}", static function (AuthUser $user): void {
            app(self::class)->created($user);
        });

        $events->listen("eloquent.deleting: {$model}", static function (AuthUser $user): void {
            app(self::class)->deleting($user);
        });

        $events->listen("eloquent.deleted: {$model}", static function (AuthUser $user): void {
            app(self::class)->deleted($user);
        });
    }

    public function created(AuthUser $user): void
    {
        $this->accounts->createPersonalAccount($user);
    }

    public function deleting(AuthUser $user): void
    {
        $this->accounts->ensurePersonCanBeDeleted($user);

        $this->owned[$user] = $this->accounts->ownedAccountIds($user);
    }

    public function deleted(AuthUser $user): void
    {
        $ids = $this->owned[$user] ?? [];

        unset($this->owned[$user]);

        $this->accounts->cleanUpAfterPersonDeleted($user->getKey(), $ids);
    }
}
