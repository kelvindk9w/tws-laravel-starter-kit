<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * As ações sobre webhooks gravadas na trilha de auditoria (`audit_events.action`)
 * — com efeito (`success`) ou recusadas (`denied`, com o motivo).
 *
 * Nunca vai para a trilha: o segredo (nem cifrado), a assinatura, o corpo do
 * evento. A URL do endpoint entra só como host.
 */
enum WebhookAuditEvent: string
{
    case EndpointCreated = 'webhook_endpoint.created';
    case EndpointUpdated = 'webhook_endpoint.updated';
    case EndpointDeleted = 'webhook_endpoint.deleted';
    case EndpointEnabled = 'webhook_endpoint.enabled';
    case EndpointDisabled = 'webhook_endpoint.disabled';
    case SecretRevealed = 'webhook_endpoint.secret_revealed';
    case SecretRotated = 'webhook_endpoint.secret_rotated';
    case TestSent = 'webhook_endpoint.test_sent';
    case DeliveryResent = 'webhook_delivery.resent';
}
