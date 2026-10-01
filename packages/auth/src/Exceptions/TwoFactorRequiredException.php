<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Exceptions;

use Illuminate\Validation\ValidationException;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

/**
 * Pedido de DESLIGAR o segundo fator enquanto a regra de obrigatoriedade
 * vale para a conta (Support\TwoFactorRequirement).
 *
 * É uma ValidationException comum (campo `two_factor`, mensagem traduzida):
 * Blade, Livewire e Inertia mostram como qualquer recusa de formulário. A
 * classe própria existe por um motivo: ela só nasce a partir da linha
 * `denied` já gravada na trilha de auditoria (TwoFactorLogin::ensureCanDisable)
 * e a carrega — quem a captura avisa a pessoa sem registrar de novo.
 */
final class TwoFactorRequiredException extends ValidationException
{
    public ?AuditEvent $event = null;

    public static function recorded(AuditEvent $event, string $reason): self
    {
        $exception = self::withMessages(['two_factor' => $reason]);
        $exception->event = $event;

        return $exception;
    }
}
