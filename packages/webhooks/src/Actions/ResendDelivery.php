<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Foundation\Audit\AuditTrail;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Delivery\DeliveryQueue;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;

/**
 * REENVIO MANUAL de uma entrega da conta atual — AUDITADO (quem pediu, qual
 * entrega, de qual endpoint).
 *
 * O MESMO evento (mesmo id e mesmo corpo: o receptor deduplica pelo id), uma
 * tentativa agora, assinada com os segredos vigentes e com o destino
 * conferido de novo. Recusado: endpoint desativado (reative antes), tentativa
 * em andamento, ou acima do limite por conta.
 */
final class ResendDelivery
{
    use GuardsWebhookAction;

    public const RATE_PER_MINUTE = 30;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookDelivery>
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, string $deliveryUuid): WebhookDelivery
    {
        $type = AuditTrail::subjectType(WebhookDelivery::class);

        $this->access()->authorizeManage($actor, WebhookAuditEvent::DeliveryResent, $deliveryUuid, $type);
        $delivery = $this->access()->delivery($actor, $deliveryUuid, WebhookAuditEvent::DeliveryResent);
        $account = Accounts::currentOrFail();

        if (! $delivery->endpoint->isActive()) {
            $this->refuse($actor, $delivery, __('webhooks.errors.endpoint_disabled'));
        }

        if ($delivery->status === DeliveryStatus::Delivering && $delivery->locked_until?->isFuture()) {
            $this->refuse($actor, $delivery, __('webhooks.errors.in_progress'));
        }

        $key = 'webhooks:resend:'.$account->uuid;

        if (RateLimiter::tooManyAttempts($key, self::RATE_PER_MINUTE)) {
            $this->refuse($actor, $delivery, __('webhooks.errors.too_many', ['seconds' => RateLimiter::availableIn($key)]));
        }

        RateLimiter::hit($key, 60);

        DB::transaction(function () use ($actor, $delivery, $account, $type): void {
            $before = $delivery->status->value;

            $delivery->forceFill([
                'status' => DeliveryStatus::Pending->value,
                'next_attempt_at' => Carbon::now(),
                'locked_until' => null,
            ])->save();

            $this->audit()->record(WebhookAuditEvent::DeliveryResent, $account, $actor, $type, $delivery->uuid, [
                'status' => ['before' => $before, 'after' => DeliveryStatus::Pending->value],
                'endpoint' => ['before' => null, 'after' => $delivery->endpoint->uuid],
                'event' => ['before' => null, 'after' => $delivery->event->uuid],
            ]);
        });

        app(DeliveryQueue::class)->push($delivery, null, true, $actor->getAuthIdentifier());

        return $delivery->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function refuse(AuthUser $actor, WebhookDelivery $delivery, string $reason): never
    {
        $this->audit()->denied(WebhookAuditEvent::DeliveryResent, Accounts::current(), $actor, $reason, AuditTrail::subjectType(WebhookDelivery::class), $delivery->uuid);

        throw ValidationException::withMessages(['delivery' => $reason]);
    }
}
