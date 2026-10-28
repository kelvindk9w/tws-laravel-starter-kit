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
 * em andamento (inclusive uma que um worker reservou entre a leitura e a
 * gravação — a volta a "pendente" é um UPDATE condicional ao estado lido),
 * entrega criada ANTES da última troca de URL do endpoint (evento antigo não
 * vai para um destino novo), ou acima do limite por conta.
 *
 * Se o reenvio falhar, as tentativas automáticas que ainda faltavam
 * continuam (DeliverySender conta só as automáticas no backoff).
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

        $urlChangedAt = $delivery->endpoint->url_changed_at;

        if ($urlChangedAt !== null && $delivery->created_at !== null && $delivery->created_at->lessThan($urlChangedAt)) {
            $this->refuse($actor, $delivery, __('webhooks.errors.url_changed'));
        }

        $key = 'webhooks:resend:'.$account->uuid;

        if (RateLimiter::tooManyAttempts($key, self::RATE_PER_MINUTE)) {
            $this->refuse($actor, $delivery, __('webhooks.errors.too_many', ['seconds' => RateLimiter::availableIn($key)]));
        }

        RateLimiter::hit($key, 60);

        $reset = DB::transaction(function () use ($actor, $delivery, $account, $type): bool {
            $before = $delivery->status->value;

            // Condicional ao estado lido: se um worker reservou a entrega
            // nesse meio-tempo, nada muda e o reenvio é recusado.
            $changed = WebhookDelivery::query()
                ->whereKey($delivery->getKey())
                ->where('status', $before)
                ->where('attempts', $delivery->attempts)
                ->update([
                    'status' => DeliveryStatus::Pending->value,
                    'next_attempt_at' => Carbon::now(),
                    'locked_until' => null,
                ]);

            if ($changed !== 1) {
                return false;
            }

            $this->audit()->record(WebhookAuditEvent::DeliveryResent, $account, $actor, $type, $delivery->uuid, [
                'status' => ['before' => $before, 'after' => DeliveryStatus::Pending->value],
                'endpoint' => ['before' => null, 'after' => $delivery->endpoint->uuid],
                'event' => ['before' => null, 'after' => $delivery->event->uuid],
            ]);

            return true;
        });

        if (! $reset) {
            $this->refuse($actor, $delivery, __('webhooks.errors.in_progress'));
        }

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
