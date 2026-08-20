<?php

declare(strict_types=1);

namespace App\Core\Logging\Exceptions;

use RuntimeException;

/**
 * Violação da imutabilidade do request log (ADR-004 — logs append-only).
 *
 * Lançada quando código da aplicação tenta UPDATE ou DELETE arbitrário
 * em request_logs via Eloquent. As únicas mutações permitidas são as
 * transições controladas de ciclo de vida (RequestLog::markFinished()
 * e RequestLog::bindTenant()).
 */
final class AppendOnlyViolationException extends RuntimeException
{
    public static function updateAttempted(): self
    {
        return new self(
            'request_logs é append-only: UPDATE arbitrário é proibido. '
            .'Use RequestLog::markFinished() ou RequestLog::bindTenant() para as transições de ciclo de vida.',
        );
    }

    public static function deleteAttempted(): self
    {
        return new self('request_logs é append-only: DELETE é proibido (ADR-004 — trilha de auditoria imutável).');
    }
}
