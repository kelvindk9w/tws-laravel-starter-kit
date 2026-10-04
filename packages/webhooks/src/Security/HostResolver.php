<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

/**
 * Resolve um nome de host para os endereços IP (v4 e v6). Uma interface para
 * a suíte trocar o DNS por um resolvedor de mentira — é assim que os testes
 * provam o rebinding (o nome muda de endereço entre o cadastro e o envio) e
 * que o envio conecta no IP conferido, sem resolver de novo.
 */
interface HostResolver
{
    /**
     * Os endereços do host (vazio = não resolve). Nunca lança: falha de DNS é
     * lista vazia.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
