<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Contracts;

use Twstec\Kit\Webhooks\Inbound\InboundEvent;

/**
 * O HANDLER do aplicativo para os eventos de uma fonte.
 *
 * Só recebe evento VERIFICADO (assinatura/segredo conferidos, janela de tempo
 * respeitada) e NÃO DUPLICADO (o mesmo id da mesma fonte chega aqui uma vez
 * por processamento bem-sucedido). Roda na FILA, fora da requisição.
 *
 * - Seja idempotente: em falha, o job tenta de novo (com backoff) e o mesmo
 *   evento volta; e o provedor pode reenviar um evento que falhou de vez.
 * - Lance exceção para pedir nova tentativa; volte normalmente para concluir.
 * - O corpo NÃO passou pelo filtro de ataques do kit (é texto de terceiros):
 *   trate o payload como entrada não confiável — valide antes de usar.
 * - Para provedor sem assinatura forte (token fixo em header), reconsulte a
 *   API do provedor antes de mudar estado: o token prova pouco.
 * - Não há conta corrente: use `Accounts::actingAs(...)` ou
 *   `Accounts::asSystem(...)` para tocar dado de conta.
 */
interface InboundWebhookHandler
{
    public function handle(InboundEvent $event): void;
}
