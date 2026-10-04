<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks;

use InvalidArgumentException;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Webhooks\Delivery\Outbox;
use Twstec\Kit\Webhooks\Models\WebhookEvent;

/**
 * A porta de entrada para o APLICATIVO disparar um webhook:
 *
 *     Webhooks::dispatch($conta, 'order.created', ['order' => ['id' => $pedido->uuid]]);
 *     Webhooks::dispatch($conta, 'order.shipped', [...], $projeto);
 *
 * - O evento precisa estar no catálogo (`webhooks.events`).
 * - Vai para cada endpoint ATIVO da conta que o assina; com projeto, só para
 *   os endpoints sem projeto ou daquele projeto.
 * - É gravado no banco (outbox) na transação em volta, se houver — dispare
 *   DENTRO da transação da mudança de negócio — e só vai para a fila depois
 *   do commit. Devolve o evento (o `uuid` é o id que o receptor recebe) ou
 *   null quando nenhum endpoint o assina.
 * - O corpo é CIFRADO no banco; não ponha nele o que o receptor não deve ver.
 */
final class Webhooks
{
    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @throws InvalidArgumentException
     */
    public static function dispatch(Account $account, string $type, array $payload, ?Project $project = null): ?WebhookEvent
    {
        return app(Outbox::class)->dispatch($account, $type, $payload, $project);
    }
}
