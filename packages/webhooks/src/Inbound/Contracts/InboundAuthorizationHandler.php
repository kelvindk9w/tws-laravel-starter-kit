<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Contracts;

use Twstec\Kit\Webhooks\Inbound\AuthorizationResponse;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;

/**
 * O HANDLER de uma fonte no modo `authorization`: o provedor espera a
 * resposta DESTA requisição (pede autorização para seguir com uma operação,
 * por exemplo), e o que o handler devolve — código HTTP e corpo JSON — é o
 * que vai para ele, sem interpretação do kit.
 *
 * Antes daqui, o kit fez o mesmo que na fonte comum: fonte conhecida e bem
 * configurada, allowlist de IP, tamanho do corpo, limite por minuto,
 * VERIFICAÇÃO sobre o corpo cru (rotação de segredo, janela de tempo) e
 * leitura do evento, com as mesmas recusas; e gravou o registro do
 * recebimento (sem o corpo). Roda NA REQUISIÇÃO: sem fila, sem nova
 * tentativa do kit.
 *
 * - O kit NÃO deduplica nem guarda a resposta. A mesma chamada repetida (o
 *   provedor tentou de novo, a rede caiu antes da resposta) chega aqui de
 *   novo, com o mesmo `id` e `attempt` maior. Quando a resposta precisa ser
 *   a mesma, grave a decisão no banco do aplicativo (INSERT com conflito
 *   ignorado sobre a unicidade fonte + id) e devolva a decisão GRAVADA — ver
 *   `examples/authorization-handler.php`.
 * - Exceção (inclusive resposta que não vira JSON) vira `500` sem corpo:
 *   nem a mensagem nem a classe saem para o provedor, que trata como falha.
 *   O kit registra só a classe (`last_error` e o log) e não reporta a
 *   exceção — a mensagem pode levar dado do evento. O detalhe que importar,
 *   registre aqui dentro.
 * - Seja rápido: o provedor tem prazo. O tempo de cada chamada vai para o
 *   log, com aviso acima do orçamento (`budget_ms` da fonte ou
 *   `webhooks.inbound.authorization_budget_ms`); nada é interrompido.
 * - O payload é texto de terceiros (não passou pelo filtro de ataques):
 *   valide antes de usar. Com verificação fraca (token fixo), decida pelo
 *   estado que o aplicativo conhece, não pelo valor que o corpo afirma.
 * - Não há conta corrente: use `Accounts::actingAs(...)` ou
 *   `Accounts::asSystem(...)` para tocar dado de conta.
 */
interface InboundAuthorizationHandler
{
    public function authorize(InboundEvent $event): AuthorizationResponse;
}
