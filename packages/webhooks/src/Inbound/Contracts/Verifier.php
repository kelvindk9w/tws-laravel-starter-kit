<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Contracts;

use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * Uma ESTRATÉGIA de verificação de webhook recebido — o que prova que o
 * corpo veio de quem diz ter vindo.
 *
 * Regras para quem implementa (as do pacote seguem todas):
 * - verifica sobre o CORPO CRU (`InboundRequest::$body`, os bytes recebidos),
 *   nunca sobre um JSON decodificado e recodificado;
 * - compara segredo e assinatura em tempo constante (`hash_equals`);
 * - aceita mais de um segredo/chave vigente (rotação);
 * - quando o esquema tem carimbo de tempo, recusa fora da janela
 *   (`InboundRequest::$receivedAt` é o "agora" — não leia o relógio);
 * - sem segredo/chave configurado, RECUSA (`Rejection::NotConfigured`) —
 *   nunca aceita por falta de configuração;
 * - não lança exceção para entrada ruim: devolve `VerificationResult::failed`.
 *
 * Uma classe própria do aplicativo entra no `verifier.driver` da fonte pelo
 * nome da classe; com um método estático `fromConfig(array $options)`, ela
 * recebe as opções da configuração (senão, é resolvida pelo container).
 */
interface Verifier
{
    public function verify(InboundRequest $request): VerificationResult;
}
