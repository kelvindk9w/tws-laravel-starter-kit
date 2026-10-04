<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Twstec\Kit\Accounts\Account\Events\AccountDeleting;
use Twstec\Kit\Accounts\Account\Events\PersonDeleted;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Models\WebhookEvent;

/**
 * Os webhooks na EXCLUSÃO (caminho único do twstec/kit-accounts):
 *
 * - CONTA excluída (AccountDeleting, dentro da transação da exclusão): saem
 *   os endpoints (com o segredo cifrado), os eventos (com o corpo cifrado),
 *   as entregas e o log de tentativas dela. Se a exclusão for desfeita, volta
 *   tudo junto. No PostgreSQL, quando a conta sai junto com a pessoa (pelo
 *   gatilho do banco, sem este evento), as chaves estrangeiras ON DELETE
 *   CASCADE fazem o mesmo na mesma sentença. Um job de entrega que chegar
 *   depois não acha a entrega e sai sem enviar.
 * - PESSOA excluída (PersonDeleted): nas contas que ficam, o que ela criou ou
 *   pediu (`created_by`) fica sem autor — o registro é da conta (o banco já
 *   faz isso com ON DELETE SET NULL; aqui vale também sem as chaves
 *   estrangeiras ligadas).
 *
 * Ficam, de propósito: a trilha de auditoria (`audit_events`) e a trilha de
 * saída (`outbound_http_logs`), que são registro do que aconteceu e saem pela
 * poda por idade de cada uma. Nenhuma delas guarda segredo, assinatura ou
 * corpo de evento.
 *
 * Webhooks não IMPEDEM exclusão: nada aqui recusa.
 */
final class WebhookLifecycle
{
    public static function register(Dispatcher $events): void
    {
        $events->listen(AccountDeleting::class, static function (AccountDeleting $event): void {
            self::eraseAccount((int) $event->account->getKey());
        });

        $events->listen(PersonDeleted::class, static function (PersonDeleted $event): void {
            self::detachPerson($event->user->getAuthIdentifier());
        });
    }

    public static function eraseAccount(int $accountId): void
    {
        Accounts::asSystem('webhooks:account-deleted', static function () use ($accountId): void {
            $deliveries = WebhookDelivery::query()->where('account_id', $accountId)->pluck('id');

            WebhookDeliveryAttempt::query()->where('account_id', $accountId)->delete();
            WebhookDelivery::query()->whereKey($deliveries)->delete();
            WebhookEvent::query()->where('account_id', $accountId)->delete();
            WebhookEndpoint::query()->where('account_id', $accountId)->delete();
        });
    }

    public static function detachPerson(mixed $userId): void
    {
        Accounts::asSystem('webhooks:person-deleted', static function () use ($userId): void {
            foreach ([WebhookEndpoint::class, WebhookEvent::class, WebhookDelivery::class, WebhookDeliveryAttempt::class] as $model) {
                $model::query()->where('created_by', $userId)->update(['created_by' => null]);
            }
        });
    }
}
