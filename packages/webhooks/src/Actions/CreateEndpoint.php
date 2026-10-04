<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Signing\SecretGenerator;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * Cria um endpoint na CONTA ATUAL.
 *
 * Ordem (cada recusa com a tentativa na trilha): papel (dono/admin) →
 * formulário → destino (SSRF, DNS de agora) → ação sensível (o token só é
 * consumido com tudo válido) → grava, com a linha da trilha na MESMA
 * transação (falha fechada).
 *
 * Por que ação sensível: um endpoint novo é um destino novo para os dados da
 * conta — numa sessão roubada, seria o caminho para tirar dado de lá.
 *
 * Devolve o endpoint e o SEGREDO EM CLARO, para a tela mostrar UMA vez. Ele
 * não volta a sair (só por RevealSecret, outra ação sensível).
 */
final class CreateEndpoint
{
    use GuardsWebhookAction;

    /**
     * @param  array<string, mixed>  $data  name, url, events (lista; `*` = todos), project (uuid ou nulo)
     * @return array{endpoint: WebhookEndpoint, secret: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, array $data, #[\SensitiveParameter] ?string $sensitiveToken): array
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::EndpointCreated);

        $valid = $this->validatedEndpoint($actor, $data, WebhookAuditEvent::EndpointCreated);

        $this->consumeSensitiveToken($actor, $sensitiveToken, WebhookAuditEvent::EndpointCreated);

        $secret = SecretGenerator::generate();

        $endpoint = DB::transaction(function () use ($actor, $valid, $secret): WebhookEndpoint {
            $endpoint = WebhookEndpoint::query()->create([
                'project_id' => $valid['project']?->getKey(),
                'created_by' => $actor->getAuthIdentifier(),
                'name' => $valid['name'],
                'url' => $valid['url'],
                'events' => $valid['events'],
                'secret' => $secret,
                'status' => EndpointStatus::Active->value,
            ]);

            $endpoint->setRelation('project', $valid['project']);

            $this->audit()->record(
                WebhookAuditEvent::EndpointCreated,
                Accounts::current(),
                $actor,
                WebhookAudit::endpointType(),
                $endpoint->uuid,
                array_map(static fn (mixed $value): array => ['before' => null, 'after' => $value], WebhookAudit::snapshot($endpoint)),
            );

            return $endpoint;
        });

        return ['endpoint' => $endpoint, 'secret' => $secret];
    }
}
