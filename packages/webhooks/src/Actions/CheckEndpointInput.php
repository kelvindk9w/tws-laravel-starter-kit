<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;

/**
 * A PRÉ-CONFERÊNCIA do formulário de endpoint, antes de a tela pedir a ação
 * sensível: o papel, os campos e o destino (SSRF, DNS de agora) — as MESMAS
 * regras que CreateEndpoint e UpdateEndpoint aplicam de novo na hora de
 * gravar (a conferência que vale é a de lá). Assim a pessoa não passa pela
 * senha de transação e pelo código por e-mail para só então saber que a URL
 * não é aceita. Destino recusado aqui também vai para a trilha.
 */
final class CheckEndpointInput
{
    use GuardsWebhookAction;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, array $data, ?string $endpointUuid = null): void
    {
        $attempt = $endpointUuid === null ? WebhookAuditEvent::EndpointCreated : WebhookAuditEvent::EndpointUpdated;

        $this->access()->authorizeManage($actor, $attempt, $endpointUuid);

        if ($endpointUuid !== null) {
            $this->access()->endpoint($actor, $endpointUuid, $attempt);
        }

        $this->validatedEndpoint($actor, $data, $attempt, $endpointUuid);
    }
}
