<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * Resultado de UMA tentativa de entrega.
 *
 * - succeeded: resposta 2xx;
 * - failed: resposta fora de 2xx (inclusive redirect, que nunca é seguido),
 *   tempo esgotado ou falha de conexão;
 * - blocked: o destino foi RECUSADO antes de qualquer conexão (SSRF: o DNS
 *   resolveu para endereço proibido, esquema não permitido) — nada saiu.
 */
enum AttemptOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return __('webhooks.attempt_outcome.'.$this->value);
    }
}
