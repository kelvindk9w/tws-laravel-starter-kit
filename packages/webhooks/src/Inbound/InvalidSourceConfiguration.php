<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use InvalidArgumentException;

/**
 * Fonte de webhook mal configurada (verificador desconhecido, handler que
 * não implementa o contrato, nome inválido). A mensagem nunca carrega
 * segredo. Na requisição vira 503 (o provedor tenta de novo depois do
 * conserto) com o motivo no log.
 */
final class InvalidSourceConfiguration extends InvalidArgumentException {}
