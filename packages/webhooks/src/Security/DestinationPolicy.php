<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

use Illuminate\Contracts\Foundation\Application;

/**
 * A POLÍTICA DE DESTINO dos webhooks — proteção contra SSRF.
 *
 * Usada no CADASTRO (e na edição e na reativação) do endpoint e de novo em
 * CADA ENVIO, com o DNS resolvido na hora: um nome que apontava para um
 * endereço público no cadastro e passou a apontar para a rede interna
 * (rebinding) é recusado no envio.
 *
 * O que é recusado (código em BlockedDestinationException::$reason):
 * - URL malformada, com espaço, barra invertida, caractere de controle ou
 *   maior que 2048 caracteres (`invalid_url`);
 * - esquema fora de https — http só fora de produção e com
 *   `webhooks.destination.require_https = false` (`scheme_not_allowed`);
 * - usuário/senha na URL (`credentials_in_url`): credencial em URL vaza em
 *   log, e o `@` é o truque clássico para confundir quem lê a URL;
 * - host que não é um nome DNS em ASCII nem um IP literal canônico — inclusive
 *   as formas que o resolvedor do sistema aceitaria como IP e confundiriam a
 *   conferência (`2130706433`, `0x7f.1`, `0177.0.0.1`, `127.1`) (`invalid_host`);
 * - nome que não resolve (`unresolvable`);
 * - QUALQUER endereço resolvido que seja de metadados de nuvem
 *   (`metadata_address`) ou não público — loopback, privado, link-local,
 *   reservado e equivalentes IPv6 (`private_address`). Basta um: um nome com
 *   um A público e outro privado é recusado inteiro.
 *
 * Redes privadas liberadas (`webhooks.destination.allowed_private_networks`)
 * valem SÓ FORA DE PRODUÇÃO, e nunca para metadados nem para as faixas
 * IpRanges::NEVER.
 *
 * O resultado (ValidatedDestination) traz a URL REMONTADA das partes
 * conferidas e o IP em que a conexão vai acontecer — o envio passa esse IP ao
 * cURL (CURLOPT_RESOLVE) e confere, antes de mandar o primeiro byte, que a
 * conexão foi mesmo para ele (CURLOPT_PREREQFUNCTION). O cURL nunca resolve o
 * nome por conta própria.
 */
final class DestinationPolicy
{
    public const MAX_URL_LENGTH = 2048;

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly Application $app,
    ) {}

    /**
     * Confere a URL e o DNS dela agora.
     *
     * @throws BlockedDestinationException
     */
    public function check(string $url): ValidatedDestination
    {
        [$scheme, $host, $port, $path, $query] = $this->parse($url);

        $isLiteral = IpRanges::isIp(trim($host, '[]'));
        $addresses = $isLiteral ? [(string) IpRanges::normalize($host)] : $this->resolve($host);

        foreach ($addresses as $address) {
            $this->assertAllowed($address, $host);
        }

        // Prefere IPv4 (o caminho mais comum de um receptor); a lista inteira
        // já foi conferida.
        $chosen = $addresses[0];

        foreach ($addresses as $address) {
            if (! str_contains($address, ':')) {
                $chosen = $address;

                break;
            }
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $canonical = $scheme.'://'.$host.($port !== $defaultPort ? ':'.$port : '').$path.($query !== '' ? '?'.$query : '');

        return new ValidatedDestination($canonical, $scheme, $host, $port, $chosen, $addresses);
    }

    /**
     * Confere UM endereço (o da conexão já aberta, na conferência antes do
     * primeiro byte).
     */
    public function addressAllowed(string $address): bool
    {
        try {
            $this->assertAllowed($address, null);

            return true;
        } catch (BlockedDestinationException) {
            return false;
        }
    }

    /**
     * HTTPS obrigatório agora? Em produção, sempre.
     */
    public function requiresHttps(): bool
    {
        return $this->app->isProduction() || config('webhooks.destination.require_https', true) !== false;
    }

    /**
     * As redes privadas liberadas que valem agora (nenhuma em produção).
     *
     * @return list<string>
     */
    public function allowedPrivateNetworks(): array
    {
        if ($this->app->isProduction()) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $range): string => trim((string) $range), (array) config('webhooks.destination.allowed_private_networks', [])),
            static fn (string $range): bool => $range !== '' && IpRanges::isValidRange($range),
        ));
    }

    /**
     * Avisos de configuração para o log a cada boot.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->app->isProduction() && config('webhooks.destination.require_https', true) === false) {
            $warnings[] = 'webhooks.destination.require_https=false IGNORADO em produção: webhooks só saem por HTTPS.';
        }

        if ($this->app->isProduction() && array_filter((array) config('webhooks.destination.allowed_private_networks', [])) !== []) {
            $warnings[] = 'webhooks.destination.allowed_private_networks IGNORADO em produção: webhooks nunca vão para a rede interna.';
        }

        foreach ((array) config('webhooks.destination.allowed_private_networks', []) as $range) {
            if (is_string($range) && trim($range) !== '' && ! IpRanges::isValidRange($range)) {
                $warnings[] = 'webhooks.destination.allowed_private_networks: faixa inválida ignorada.';
            }
        }

        return $warnings;
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: string, 4: string}
     *
     * @throws BlockedDestinationException
     */
    private function parse(string $url): array
    {
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\s\\\\\x00-\x1F\x7F]/', $url) === 1 || ! mb_check_encoding($url, 'ASCII')) {
            throw new BlockedDestinationException('invalid_url');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new BlockedDestinationException('invalid_url');
        }

        if (isset($parts['user']) || isset($parts['pass']) || str_contains($url, '@')) {
            throw new BlockedDestinationException('credentials_in_url');
        }

        $scheme = strtolower($parts['scheme']);
        $allowed = $this->requiresHttps() ? ['https'] : ['https', 'http'];

        if (! in_array($scheme, $allowed, true)) {
            throw new BlockedDestinationException('scheme_not_allowed');
        }

        // A URL tem de começar exatamente por "esquema://" (nada de
        // "https:host" nem barras a mais que cada leitor entende de um jeito).
        if (preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://[^/]#', $url) !== 1) {
            throw new BlockedDestinationException('invalid_url');
        }

        $host = strtolower($parts['host']);

        if (! $this->validHost($host)) {
            throw new BlockedDestinationException('invalid_host', $host);
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new BlockedDestinationException('invalid_url', $host);
        }

        $path = $parts['path'] ?? '';
        $path = $path === '' ? '/' : $path;

        return [$scheme, $host, $port, $path, $parts['query'] ?? ''];
    }

    /**
     * Nome DNS em ASCII (com um rótulo final que não é só número) ou IP
     * literal canônico — IPv4 em quatro decimais sem zero à esquerda, IPv6
     * entre colchetes.
     */
    private function validHost(string $host): bool
    {
        if (str_starts_with($host, '[')) {
            $inner = substr($host, 1, -1);

            return str_ends_with($host, ']') && filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        // Todo rótulo é número decimal ou hexadecimal (`0x…`) = tentativa de
        // IP (inteiro, octal, hex, abreviado): tem de ser o IPv4 canônico.
        $labels = explode('.', $host);
        $numeric = array_filter($labels, static fn (string $label): bool => preg_match('/^(?:0x[0-9a-f]*|\d+)$/', $label) === 1);

        if (count($numeric) === count($labels)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                && preg_match('/(^|\.)0\d/', $host) !== 1;
        }

        if (strlen($host) > 253 || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            return false;
        }

        // O último rótulo nunca é só número (senão o resolvedor do sistema
        // leria como IP: "127.1", "10.1").
        return preg_match('/^\d+$/', (string) end($labels)) !== 1;
    }

    /**
     * @return list<string>
     *
     * @throws BlockedDestinationException
     */
    private function resolve(string $host): array
    {
        $addresses = [];

        foreach ($this->resolver->resolve($host) as $address) {
            $normalized = IpRanges::normalize((string) $address);

            if ($normalized === null) {
                throw new BlockedDestinationException('unresolvable', $host);
            }

            $addresses[] = $normalized;
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            throw new BlockedDestinationException('unresolvable', $host);
        }

        return $addresses;
    }

    /**
     * @throws BlockedDestinationException
     */
    private function assertAllowed(string $address, ?string $host): void
    {
        $normalized = IpRanges::normalize($address);

        if ($normalized === null) {
            throw new BlockedDestinationException('private_address', $host, $address);
        }

        if (IpRanges::isMetadata($normalized)) {
            throw new BlockedDestinationException('metadata_address', $host, $normalized);
        }

        if (IpRanges::isNever($normalized)) {
            throw new BlockedDestinationException('private_address', $host, $normalized);
        }

        if (IpRanges::inAny($normalized, $this->allowedPrivateNetworks())) {
            return;
        }

        if (IpRanges::isNonPublic($normalized)) {
            throw new BlockedDestinationException('private_address', $host, $normalized);
        }
    }
}
