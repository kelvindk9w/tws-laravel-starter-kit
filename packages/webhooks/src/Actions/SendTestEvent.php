<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Delivery\Outbox;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Models\WebhookEvent;
use Twstec\Kit\Webhooks\Support\EventCatalog;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * Envia o evento de TESTE (`webhook.ping`) para UM endpoint ativo da conta
 * atual — pelo mesmo caminho de um evento real (outbox, fila, assinatura,
 * conferência do destino). Na trilha. Limitado por conta
 * (`RATE_PER_MINUTE`): o botão não vira ferramenta de varredura.
 */
final class SendTestEvent
{
    use GuardsWebhookAction;

    public const RATE_PER_MINUTE = 10;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, string $endpointUuid): WebhookEvent
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::TestSent, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, WebhookAuditEvent::TestSent);
        $account = Accounts::currentOrFail();

        if (! $endpoint->isActive()) {
            throw ValidationException::withMessages(['endpoint' => __('webhooks.errors.endpoint_disabled')]);
        }

        $key = 'webhooks:test:'.$account->uuid;

        if (RateLimiter::tooManyAttempts($key, self::RATE_PER_MINUTE)) {
            throw ValidationException::withMessages(['endpoint' => __('webhooks.errors.too_many', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        RateLimiter::hit($key, 60);

        return DB::transaction(function () use ($actor, $endpoint, $account): WebhookEvent {
            $this->audit()->record(WebhookAuditEvent::TestSent, $account, $actor, WebhookAudit::endpointType(), $endpoint->uuid);

            /** @var WebhookEvent */
            return app(Outbox::class)->record($account, EventCatalog::PING, [
                'message' => 'ping',
                'endpoint' => $endpoint->uuid,
            ], null, [$endpoint]);
        });
    }
}
