<?php

declare(strict_types=1);

namespace Twstec\Kit\Accounts\Tenancy;

use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Contexto do tenant da requisição corrente.
 *
 * Preenchido pelo middleware ResolveTenant após autenticar a secret key:
 * o TENANT é o usuário dono da chave. Acesso global via helpers tenant() e
 * tenantKey() (src/Tenancy/helpers.php do pacote).
 *
 * Registrado como singleton no container — o ciclo de vida "por requisição"
 * é garantido pelo PHP-FPM (shared-nothing). SE Octane/worker mode for
 * adotado um dia, este estado DEVE ser resetado entre requisições
 * (worker-safety: senão o tenant de uma vaza para a próxima).
 */
final class TenantContext
{
    private ?AuthUser $user = null;

    private ?ApiKey $apiKey = null;

    /**
     * Resolve o tenant da requisição (chamado somente pelo ResolveTenant).
     */
    public function resolve(AuthUser $user, ApiKey $apiKey): void
    {
        $this->user = $user;
        $this->apiKey = $apiKey;
    }

    /**
     * Usuário dono da chave autenticada (o tenant). Null fora de rota tenant.
     */
    public function user(): ?AuthUser
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
