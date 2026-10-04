<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Signing;

/**
 * Gera o SEGREDO de assinatura de um endpoint, no servidor: o prefixo
 * `whsk_` (para a pessoa reconhecer o que é numa ferramenta de segredos) e
 * 32 bytes aleatórios (`random_bytes`, CSPRNG) em base64url — 256 bits.
 *
 * A chave do HMAC é o texto INTEIRO (com o prefixo), do jeito que a tela
 * mostra: o receptor usa o que copiou, sem decodificar nada.
 */
final class SecretGenerator
{
    public const PREFIX = 'whsk_';

    public static function generate(): string
    {
        return self::PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
