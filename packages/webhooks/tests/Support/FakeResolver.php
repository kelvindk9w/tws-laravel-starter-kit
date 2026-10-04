<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Tests\Support;

use Twstec\Kit\Webhooks\Security\HostResolver;

/**
 * DNS de mentira da suíte: cada nome responde o que o teste mandou — e o
 * teste pode trocar a resposta no meio (rebinding). Conta as consultas por
 * nome, para provar quando o pacote resolveu.
 */
final class FakeResolver implements HostResolver
{
    /**
     * @var array<string, int>
     */
    public array $lookups = [];

    /**
     * @param  array<string, list<string>>  $records
     */
    public function __construct(public array $records = []) {}

    public function resolve(string $host): array
    {
        $this->lookups[$host] = ($this->lookups[$host] ?? 0) + 1;

        return $this->records[$host] ?? [];
    }
}
