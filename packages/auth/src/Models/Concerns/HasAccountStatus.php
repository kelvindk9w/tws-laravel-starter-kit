<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Twstec\Kit\Auth\Enums\UserStatus;

/**
 * Status da conta (`status`: active, pending, blocked). Deny-by-default: só
 * conta ATIVA opera — login, sessão web (EnsureAccountIsActive) e API
 * perguntam aqui.
 *
 * A instância nova já nasce `active` (o mesmo default da coluna): sem isso, o
 * status ficaria null até o primeiro refresh depois do INSERT, e a conta
 * recém-criada pareceria inativa.
 */
trait HasAccountStatus
{
    public function initializeHasAccountStatus(): void
    {
        $this->mergeCasts(['status' => UserStatus::class]);

        if (! array_key_exists('status', $this->attributes)) {
            $this->attributes['status'] = UserStatus::Active->value;
        }
    }

    /**
     * A conta está ativa? (Login é deny-by-default.)
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
