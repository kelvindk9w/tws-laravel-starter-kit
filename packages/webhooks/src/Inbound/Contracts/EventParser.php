<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Contracts;

use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\MalformedPayload;
use Twstec\Kit\Webhooks\Inbound\ParsedEvent;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * Lê id, tipo, data e payload de um corpo JÁ VERIFICADO. O padrão do pacote
 * (Inbound\JsonEventParser) lê JSON com os caminhos da configuração da fonte;
 * um formato diferente (formulário, XML, envelope próprio) entra por uma
 * classe do aplicativo em `parser` da fonte.
 *
 * Corpo que não se lê lança Inbound\MalformedPayload (resposta 400, motivo só
 * no log interno).
 */
interface EventParser
{
    /**
     * @throws MalformedPayload
     */
    public function parse(InboundRequest $request, VerificationResult $verification): ParsedEvent;
}
