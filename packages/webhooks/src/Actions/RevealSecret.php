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
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * REVELA o segredo atual de um endpoint — ação sensível, com a linha na
 * trilha (quem revelou, quando, de onde; nunca o valor). A tela mostra o
 * valor uma vez e o descarta.
 */
final class RevealSecret
{
    use GuardsWebhookAction;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     */
    public function handle(AuthUser $actor, string $endpointUuid, #[\SensitiveParameter] ?string $sensitiveToken): string
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::SecretRevealed, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, WebhookAuditEvent::SecretRevealed);

        $this->consumeSensitiveToken($actor, $sensitiveToken, WebhookAuditEvent::SecretRevealed, $endpoint->uuid);

        return DB::transaction(function () use ($actor, $endpoint): string {
            $this->audit()->record(WebhookAuditEvent::SecretRevealed, Accounts::current(), $actor, WebhookAudit::endpointType(), $endpoint->uuid);

            return $endpoint->secret;
        });
    }
}
