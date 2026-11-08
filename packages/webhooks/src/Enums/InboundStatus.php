<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * O estado de um webhook RECEBIDO (webhook_inbound_events.status).
 *
 * - `received`: verificado, registrado e na fila (ou esperando nova
 *   tentativa depois de uma falha do handler);
 * - `processing`: um worker o pegou (com prazo — `locked_until`);
 * - `processed`: o handler concluiu; repetição devolve 2xx sem reprocessar;
 * - `failed`: o handler falhou em todas as tentativas; um reenvio do
 *   provedor (o mesmo id) volta a pô-lo na fila, uma vez.
 */
enum InboundStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
