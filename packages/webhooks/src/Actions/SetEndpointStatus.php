<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Delivery\EndpointHealth;
use Twstec\Kit\Webhooks\Enums\DisabledReason;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\BlockedDestinationException;
use Twstec\Kit\Webhooks\Security\DestinationPolicy;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * Desativa ou REATIVA um endpoint da conta atual.
 *
 * - Desativar: as entregas em aberto dele saem como falhas (o reenvio manual
 *   continua possível depois de reativar).
 * - Reativar: o destino é conferido de novo (SSRF, DNS de agora) e as falhas
 *   seguidas voltam a zero.
 */
final class SetEndpointStatus
{
    use GuardsWebhookAction;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, string $endpointUuid, bool $active): WebhookEndpoint
    {
        $attempt = $active ? WebhookAuditEvent::EndpointEnabled : WebhookAuditEvent::EndpointDisabled;

        $this->access()->authorizeManage($actor, $attempt, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, $attempt);

        if ($endpoint->isActive() === $active) {
            return $endpoint;
        }

        if ($active) {
            try {
                app(DestinationPolicy::class)->check($endpoint->url);
            } catch (BlockedDestinationException $blocked) {
                $this->audit()->denied($attempt, Accounts::current(), $actor, $blocked->reason.': '.$blocked->getMessage(), WebhookAudit::endpointType(), $endpoint->uuid);

                throw ValidationException::withMessages(['url' => $blocked->getMessage()]);
            }
        }

        return DB::transaction(function () use ($actor, $endpoint, $active, $attempt): WebhookEndpoint {
            $before = $endpoint->status->value;

            $endpoint->forceFill($active ? [
                'status' => EndpointStatus::Active->value,
                'disabled_reason' => null,
                'disabled_at' => null,
                'consecutive_failures' => 0,
            ] : [
                'status' => EndpointStatus::Disabled->value,
                'disabled_reason' => DisabledReason::Manual->value,
                'disabled_at' => Carbon::now(),
            ])->save();

            if (! $active) {
                EndpointHealth::closeOpenDeliveries($endpoint);
            }

            $this->audit()->record($attempt, Accounts::current(), $actor, WebhookAudit::endpointType(), $endpoint->uuid, [
                'status' => ['before' => $before, 'after' => $endpoint->status->value],
            ]);

            return $endpoint;
        });
    }
}
