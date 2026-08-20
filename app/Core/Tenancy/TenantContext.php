<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;

/**
 * Contexto do tenant da requisição corrente (ADR-010).
 *
 * Preenchido pelo middleware ResolveTenant após autenticar a secret key:
 * o TENANT é o usuário dono da chave. Acesso global via helpers tenant() e
 * tenantKey() (app/Core/Support/helpers.php).
 *
 * Registrado como singleton no container — o ciclo de vida "por requisição"
 * é garantido pelo PHP-FPM (shared-nothing). SE Octane/worker mode for
 * adotado um dia, este estado DEVE ser resetado entre requisições
 * (checklist item 28 — worker-safety).
 */
final class TenantContext
{
    private ?User $user = null;

    private ?ApiKey $apiKey = null;

    /**
     * Resolve o tenant da requisição (chamado somente pelo ResolveTenant).
     */
    public function resolve(User $user, ApiKey $apiKey): void
    {
        $this->user = $user;
        $this->apiKey = $apiKey;
    }

    /**
     * Usuário dono da chave autenticada (o tenant). Null fora de rota tenant.
     */
    public function user(): ?User
    {
        return $this->user;
    }

    /**
     * Chave de API que autenticou a requisição. Null fora de rota tenant.
     */
    public function apiKey(): ?ApiKey
    {
        return $this->apiKey;
    }

    public function resolved(): bool
    {
        return $this->user !== null;
    }
}
