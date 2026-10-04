<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * Altera nome, URL, eventos e projeto de um endpoint da conta atual — com o
 * destino conferido de novo e AÇÃO SENSÍVEL (mudar a URL ou os eventos muda
 * para onde e o que sai da conta). O segredo não muda aqui (RotateSecret).
 */
final class UpdateEndpoint
{
    use GuardsWebhookAction;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, string $endpointUuid, array $data, #[\SensitiveParameter] ?string $sensitiveToken): WebhookEndpoint
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::EndpointUpdated, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, WebhookAuditEvent::EndpointUpdated);

        $valid = $this->validatedEndpoint($actor, $data, WebhookAuditEvent::EndpointUpdated, $endpoint->uuid);

        $this->consumeSensitiveToken($actor, $sensitiveToken, WebhookAuditEvent::EndpointUpdated, $endpoint->uuid);

        return DB::transaction(function () use ($actor, $endpoint, $valid): WebhookEndpoint {
            $before = WebhookAudit::snapshot($endpoint);

            $endpoint->forceFill([
                'name' => $valid['name'],
                'url' => $valid['url'],
                'events' => $valid['events'],
                'project_id' => $valid['project']?->getKey(),
            ])->save();

            $endpoint->setRelation('project', $valid['project']);
            $after = WebhookAudit::snapshot($endpoint);

            $changes = [];

            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = ['before' => $before[$field], 'after' => $value];
                }
            }

            // A URL pode mudar só no caminho (o host fica igual): a linha
            // registra que mudou, sem o valor.
            if ($endpoint->wasChanged('url') && ! isset($changes['host'])) {
                $changes['url'] = ['before' => '[changed]', 'after' => '[changed]'];
            }

            $this->audit()->record(WebhookAuditEvent::EndpointUpdated, Accounts::current(), $actor, WebhookAudit::endpointType(), $endpoint->uuid, $changes);

            return $endpoint;
        });
    }
}
