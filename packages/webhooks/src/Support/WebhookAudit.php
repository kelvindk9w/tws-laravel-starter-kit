<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Support;

use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Support\AccountAudit;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Foundation\Audit\AuditTrail;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;

/**
 * A trilha de auditoria dos webhooks, pela porta das contas (AccountAudit →
 * AuditTrail do foundation, que redige): quem, em qual conta, em qual
 * endpoint ou entrega, de onde (IP, User-Agent) e o correlation_id.
 *
 * O que entra no "antes/depois" é escolhido aqui, campo a campo: nome, HOST
 * do destino (nunca a URL inteira), eventos, projeto, estado. NUNCA o
 * segredo (nem cifrado), a assinatura ou o corpo de um evento.
 *
 * - record(): com efeito, DENTRO da transação da mudança (falha fechada: sem
 *   linha na trilha, a mudança é desfeita);
 * - denied(): recusada, fora de transação, antes de a exceção sair.
 */
final class WebhookAudit
{
    public function __construct(private readonly AccountAudit $audit) {}

    /**
     * @param  array<string, array{before?: mixed, after?: mixed}>  $changes
     */
    public function record(WebhookAuditEvent $event, ?Account $account, ?AuthUser $actor, string $subjectType, ?string $subjectUuid, array $changes = []): void
    {
        $this->audit->record($event, $account, $actor, null, $changes, $subjectType, $subjectUuid);
    }

    public function denied(WebhookAuditEvent $event, ?Account $account, ?AuthUser $actor, string $reason, string $subjectType, ?string $subjectUuid = null): void
    {
        $this->audit->denied($event, $account, $actor, $reason, null, $subjectType, $subjectUuid);
    }

    /**
     * O retrato do endpoint que pode ir para a trilha.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(WebhookEndpoint $endpoint): array
    {
        return [
            'name' => $endpoint->name,
            'host' => $endpoint->host(),
            'events' => array_values($endpoint->events),
            'project' => $endpoint->project?->uuid,
            'status' => $endpoint->status->value,
        ];
    }

    public static function endpointType(): string
    {
        return AuditTrail::subjectType(WebhookEndpoint::class);
    }
}
