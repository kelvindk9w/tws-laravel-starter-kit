<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * Exclui um endpoint da conta atual, com as entregas e o log dele (os
 * eventos ficam: podem ter ido para outros endpoints; a limpeza por idade os
 * leva). Diminui o que sai da conta: não pede ação sensível, mas exige o
 * papel e fica na trilha.
 */
final class DeleteEndpoint
{
    use GuardsWebhookAction;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     */
    public function handle(AuthUser $actor, string $endpointUuid): void
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::EndpointDeleted, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, WebhookAuditEvent::EndpointDeleted);

        DB::transaction(function () use ($actor, $endpoint): void {
            $before = WebhookAudit::snapshot($endpoint);
            $deliveries = WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->getKey())->pluck('id');

            WebhookDeliveryAttempt::query()->whereIn('webhook_delivery_id', $deliveries)->delete();
            WebhookDelivery::query()->whereKey($deliveries)->delete();
            $endpoint->delete();

            $this->audit()->record(
                WebhookAuditEvent::EndpointDeleted,
                Accounts::current(),
                $actor,
                WebhookAudit::endpointType(),
                $endpoint->uuid,
                array_map(static fn (mixed $value): array => ['before' => $value, 'after' => null], $before),
            );
        });
    }
}
