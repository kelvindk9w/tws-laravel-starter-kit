<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use RuntimeException;

/**
 * Corpo verificado que não se lê (JSON inválido, raiz que não é objeto,
 * formato diferente do esperado). A mensagem nunca carrega o corpo.
 */
final class MalformedPayload extends RuntimeException {}
