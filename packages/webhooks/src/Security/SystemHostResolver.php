<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

/**
 * O DNS do sistema: IPv4 por `gethostbynamel()` (que consulta o /etc/hosts e
 * o resolvedor do sistema, como o cURL faria) e IPv6 por `dns_get_record()`
 * (registros AAAA). Os endereços daqui são CONFERIDOS pela política de destino
 * e o envio conecta num deles — o cURL não resolve o nome de novo (ver
 * DestinationPolicy e Delivery\DeliverySender).
 */
final class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];

        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4)) {
            foreach ($ipv4 as $ip) {
                $addresses[] = $ip;
            }
        }

        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
