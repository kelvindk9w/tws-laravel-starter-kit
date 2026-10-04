<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

/**
 * As faixas de endereço que um webhook NUNCA alcança, e as conferências de
 * CIDR em binário (inet_pton — nada de comparação de texto, que um
 * `0177.0.0.1` ou um `::ffff:7f00:1` enganaria).
 *
 * Duas listas:
 *
 * - NEVER: nem a lista de redes liberadas de desenvolvimento abre — metadados
 *   de nuvem (AWS/GCP/Azure/OpenStack 169.254.169.254, ECS 169.254.170.2,
 *   Alibaba 100.100.100.200, Azure 168.63.129.16, Oracle 192.0.0.192, AWS IPv6
 *   fd00:ec2::254), link-local inteiro (v4 e v6), "esta rede" (0.0.0.0/8),
 *   multicast, broadcast, reservado, e as FORMAS DE TRANSIÇÃO do IPv6 que
 *   carregam um IPv4 dentro (::ffff:0:0/96 mapeado, ::/96 compatível,
 *   64:ff9b::/96 NAT64, 2002::/16 6to4, 2001::/32 Teredo) — recusadas
 *   inteiras: o IPv4 de dentro poderia ser privado, e um receptor legítimo
 *   não publica endereço assim.
 * - PRIVATE: loopback, redes privadas (RFC 1918), CGNAT, ULA (fc00::/7),
 *   documentação e testes de rede. Só a lista de redes liberadas (fora de
 *   produção) abre.
 *
 * Por último, `FILTER_FLAG_GLOBAL_RANGE` do PHP (endereço que não é global
 * segundo a RFC 6890) recusa o que estas listas porventura não cobrirem.
 */
final class IpRanges
{
    /**
     * @var list<string>
     */
    public const METADATA = [
        '169.254.169.254/32',
        '169.254.170.2/32',
        '100.100.100.200/32',
        '168.63.129.16/32',
        '192.0.0.192/32',
        'fd00:ec2::254/128',
    ];

    /**
     * @var list<string>
     */
    public const NEVER = [
        '0.0.0.0/8',
        '169.254.0.0/16',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',
        '::/128',
        '::/96',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '2001::/32',
        '2002::/16',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * @var list<string>
     */
    public const PRIVATE = [
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '::1/128',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fec0::/10',
    ];

    /**
     * O endereço é um IP válido (v4 em quatro números decimais, ou v6)?
     */
    public static function isIp(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Forma canônica (inet_ntop) — a mesma para `::1` e `0:0:0:0:0:0:0:1`.
     */
    public static function normalize(string $address): ?string
    {
        $packed = @inet_pton(trim($address, '[]'));

        if ($packed === false) {
            return null;
        }

        $text = inet_ntop($packed);

        return $text === false ? null : $text;
    }

    public static function isMetadata(string $address): bool
    {
        return self::inAny($address, self::METADATA);
    }

    /**
     * Endereço que NADA libera.
     */
    public static function isNever(string $address): bool
    {
        return self::isMetadata($address) || self::inAny($address, self::NEVER);
    }

    /**
     * Endereço que não é público (privado, loopback, reservado ou não global
     * segundo o PHP).
     */
    public static function isNonPublic(string $address): bool
    {
        if (self::isNever($address) || self::inAny($address, self::PRIVATE)) {
            return true;
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false;
    }

    /**
     * O endereço está em alguma das faixas (CIDR)? Faixa inválida é ignorada;
     * endereço inválido nunca casa.
     *
     * @param  list<string>  $ranges
     */
    public static function inAny(string $address, array $ranges): bool
    {
        $packed = @inet_pton(trim($address, '[]'));

        if ($packed === false) {
            return false;
        }

        foreach ($ranges as $range) {
            if (self::inRange($packed, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A faixa é um CIDR válido (`10.0.0.0/8`, `fd00::/8`, ou um IP sozinho)?
     */
    public static function isValidRange(string $range): bool
    {
        return self::parseRange($range) !== null;
    }

    private static function inRange(string $packed, string $range): bool
    {
        $parsed = self::parseRange($range);

        if ($parsed === null) {
            return false;
        }

        [$network, $bits] = $parsed;

        if (strlen($network) !== strlen($packed)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;

        if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function parseRange(string $range): ?array
    {
        $range = trim($range);
        [$address, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $packed = @inet_pton((string) $address);

        if ($packed === false) {
            return null;
        }

        $max = strlen($packed) * 8;

        if ($bits === null) {
            return [$packed, $max];
        }

        if (! ctype_digit($bits) || (int) $bits > $max) {
            return null;
        }

        return [$packed, (int) $bits];
    }
}
