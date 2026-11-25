<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * O MODO de uma fonte de webhooks recebidos (`mode` na configuração da
 * fonte; `mode:` no registro pelo código).
 *
 * - `queue` (o padrão): o aviso é registrado e deduplicado pelo kit, a
 *   resposta sai na hora (202/200) e o handler
 *   (`Inbound\Contracts\InboundWebhookHandler`) roda na FILA, com novas
 *   tentativas.
 * - `authorization`: o provedor espera a RESPOSTA desta requisição (pede
 *   autorização para seguir, por exemplo). Depois das mesmas recusas da
 *   fonte comum (IP, tamanho, limite por minuto, verificação, leitura), o
 *   handler (`Inbound\Contracts\InboundAuthorizationHandler`) roda NA
 *   REQUISIÇÃO, sem fila, e o código HTTP e o corpo JSON que ele devolve vão
 *   ao provedor sem interpretação. Deduplicar e devolver a mesma resposta
 *   para a mesma chamada é com o handler.
 */
enum InboundMode: string
{
    case Queue = 'queue';
    case Authorization = 'authorization';
}
