<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use InvalidArgumentException;

/**
 * A RESPOSTA de um handler de autorização: o código HTTP e o corpo JSON que
 * o kit devolve ao provedor, sem interpretar (`new JsonResponse($body,
 * $status)`). O formato do corpo é o que o provedor espera — o kit não lê
 * nem muda nada dele.
 *
 *     return new AuthorizationResponse(200, ['status' => 'APPROVED']);
 *
 * O código é 2xx, 4xx ou 5xx — o que um provedor lê como sim, não ou
 * falha. 1xx não é resposta final e 3xx não tem sentido numa chamada entre
 * servidores (sem `Location`, e o provedor não deve ser levado a outro
 * endereço): lançam InvalidArgumentException, que dentro do handler vira
 * 500 sem detalhe, como qualquer exceção dele. Corpo que não vira JSON
 * (texto fora do UTF-8, INF/NAN) também vira 500.
 */
final readonly class AuthorizationResponse
{
    /**
     * @param  array<array-key, mixed>  $body  o corpo JSON (lista ou objeto); `[]` vai como `[]`
     */
    public function __construct(
        public int $status,
        public array $body = [],
    ) {
        if ($status < 200 || $status > 599 || ($status >= 300 && $status < 400)) {
            throw new InvalidArgumentException('O código HTTP da resposta de autorização precisa ser 2xx, 4xx ou 5xx.');
        }
    }
}
