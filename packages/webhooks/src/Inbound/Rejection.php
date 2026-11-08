<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

/**
 * Por que um webhook recebido foi RECUSADO. Vai só para o log interno — o
 * chamador recebe o status HTTP, sem o motivo (não ensinamos quem tenta
 * forjar o que falta).
 */
enum Rejection: string
{
    /** A fonte não tem segredo/chave configurado: recusa, nunca aceita. */
    case NotConfigured = 'not_configured';

    /** O cabeçalho de assinatura/token não veio. */
    case MissingSignature = 'missing_signature';

    /** O cabeçalho veio num formato que o verificador não lê. */
    case MalformedSignature = 'malformed_signature';

    /** Nenhum segredo/chave vigente confere. */
    case InvalidSignature = 'invalid_signature';

    /** O esquema exige carimbo de tempo e ele não veio (ou não é número). */
    case MissingTimestamp = 'missing_timestamp';

    /** Carimbo de tempo mais velho que a janela (repetição). */
    case StaleTimestamp = 'stale_timestamp';

    /** Carimbo de tempo à frente do relógio além da janela. */
    case FutureTimestamp = 'future_timestamp';

    /** O conteúdo assinado pede o id do evento e ele não veio. */
    case MissingEventId = 'missing_event_id';
}
