<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * O que aconteceu ao pôr um webhook recebido na fila (#120).
 *
 * - `Queued`: o job entrou na fila (ou, com fila síncrona, rodou e deu certo);
 * - `QueueUnavailable`: a fila recusou — o registro fica `received`, fora da
 *   fila (`queued_at` nulo), e o `webhooks:requeue-inbound` tenta de novo;
 * - `HandlerFailed`: só com fila síncrona — o job rodou na hora e o handler
 *   falhou; o registro foi a `failed`, como na fila assíncrona.
 *
 * Em todos os casos o aviso autêntico já está gravado: a resposta ao
 * provedor é 202.
 */
enum InboundDispatchOutcome: string
{
    case Queued = 'queued';
    case QueueUnavailable = 'queue_unavailable';
    case HandlerFailed = 'handler_failed';
}
