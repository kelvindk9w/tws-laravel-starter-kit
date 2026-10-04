<?php

declare(strict_types=1);

use Twstec\Kit\Webhooks\Security\BlockedDestinationException;
use Twstec\Kit\Webhooks\Security\DestinationPolicy;

// =============================================================================
// SSRF — a política de destino, endereço a endereço. O DNS é o de mentira da
// suíte (FakeResolver): nada sai da máquina.
// =============================================================================

function destinationReason(string $url): ?string
{
    try {
        app(DestinationPolicy::class)->check($url);

        return null;
    } catch (BlockedDestinationException $blocked) {
        return $blocked->reason;
    }
}

it('aceita um destino HTTPS público e devolve a URL remontada e o IP da conexão', function (): void {
    $destination = app(DestinationPolicy::class)->check('https://hooks.example.com/webhooks?origem=kit#ignorado');

    expect($destination->url)->toBe('https://hooks.example.com/webhooks?origem=kit')
        ->and($destination->host)->toBe('hooks.example.com')
        ->and($destination->port)->toBe(443)
        ->and($destination->address)->toBe('93.184.215.14')
        ->and($destination->curlResolveEntry())->toBe('hooks.example.com:443:93.184.215.14');
});

it('recusa loopback, metadados de nuvem, rede privada, IPv6 equivalentes e formas disfarçadas de IP', function (string $url, string $reason): void {
    expect(destinationReason($url))->toBe($reason);
})->with([
    'loopback 127.0.0.1' => ['https://127.0.0.1/hook', 'private_address'],
    'loopback 127.255.0.9' => ['https://127.255.0.9/hook', 'private_address'],
    'metadados 169.254.169.254' => ['https://169.254.169.254/latest/meta-data', 'metadata_address'],
    'metadados ECS' => ['https://169.254.170.2/v2/credentials', 'metadata_address'],
    'metadados Alibaba' => ['https://100.100.100.200/', 'metadata_address'],
    'link-local' => ['https://169.254.10.10/', 'private_address'],
    '10/8 início' => ['https://10.0.0.1/', 'private_address'],
    '10/8 fim' => ['https://10.255.255.254/', 'private_address'],
    '172.16/12' => ['https://172.20.1.1/', 'private_address'],
    '192.168/16' => ['https://192.168.1.10/', 'private_address'],
    'CGNAT' => ['https://100.64.0.1/', 'private_address'],
    'esta rede' => ['https://0.0.0.0/', 'private_address'],
    'IPv6 ::1' => ['https://[::1]/hook', 'private_address'],
    'IPv6 longo de ::1' => ['https://[0:0:0:0:0:0:0:1]/hook', 'private_address'],
    'IPv6 mapeado de 127.0.0.1' => ['https://[::ffff:127.0.0.1]/', 'private_address'],
    'IPv6 mapeado de 169.254.169.254' => ['https://[::ffff:a9fe:a9fe]/', 'private_address'],
    'IPv6 ULA' => ['https://[fd12:3456::1]/', 'private_address'],
    'IPv6 metadados AWS' => ['https://[fd00:ec2::254]/', 'metadata_address'],
    'IPv6 link-local' => ['https://[fe80::1]/', 'private_address'],
    'NAT64 de IPv4 privado' => ['https://[64:ff9b::a00:1]/', 'private_address'],
    '6to4' => ['https://[2002:a00:1::1]/', 'private_address'],
    'inteiro decimal' => ['https://2130706433/', 'invalid_host'],
    'hexadecimal' => ['https://0x7f.0x0.0x0.0x1/', 'invalid_host'],
    'octal' => ['https://0177.0.0.1/', 'invalid_host'],
    'abreviado' => ['https://127.1/', 'invalid_host'],
    'último rótulo numérico' => ['https://hooks.10/', 'invalid_host'],
    'usuário e senha' => ['https://user:pass@hooks.example.com/', 'credentials_in_url'],
    'arroba para enganar' => ['https://hooks.example.com@127.0.0.1/', 'credentials_in_url'],
    'barra invertida' => ['https://hooks.example.com\\@127.0.0.1/', 'invalid_url'],
    'espaço' => ['https://hooks.example.com /x', 'invalid_url'],
    'sem barras' => ['https:hooks.example.com/x', 'invalid_url'],
    'não ASCII' => ['https://exemplo.com.br/ação', 'invalid_url'],
    'gopher' => ['gopher://hooks.example.com/', 'scheme_not_allowed'],
    'file' => ['file:///etc/passwd', 'invalid_url'],
    'http em ambiente com HTTPS obrigatório' => ['http://hooks.example.com/', 'scheme_not_allowed'],
]);

it('recusa um domínio que resolve para IP privado — e o que tem UM endereço privado entre públicos', function (): void {
    $this->dns->records['interno.example.com'] = ['10.0.0.5'];
    $this->dns->records['misto.example.com'] = ['93.184.215.14', '192.168.0.10'];
    $this->dns->records['v6.example.com'] = ['::1'];
    $this->dns->records['metadados.example.com'] = ['169.254.169.254'];

    expect(destinationReason('https://interno.example.com/hook'))->toBe('private_address')
        ->and(destinationReason('https://misto.example.com/hook'))->toBe('private_address')
        ->and(destinationReason('https://v6.example.com/hook'))->toBe('private_address')
        ->and(destinationReason('https://metadados.example.com/hook'))->toBe('metadata_address');
});

it('recusa nome que não resolve', function (): void {
    expect(destinationReason('https://nao-existe.example.com/hook'))->toBe('unresolvable');
});

it('fora de produção, a lista de redes liberadas abre a rede privada — nunca metadados nem link-local', function (): void {
    config([
        'webhooks.destination.require_https' => false,
        'webhooks.destination.allowed_private_networks' => ['127.0.0.1/32', '10.0.0.0/8', '169.254.0.0/16'],
    ]);

    expect(destinationReason('http://127.0.0.1:8080/hook'))->toBeNull()
        ->and(destinationReason('http://10.1.2.3/hook'))->toBeNull()
        ->and(destinationReason('http://192.168.0.1/hook'))->toBe('private_address')
        ->and(destinationReason('http://169.254.169.254/'))->toBe('metadata_address')
        ->and(destinationReason('http://169.254.1.1/'))->toBe('private_address');
});

it('em produção, HTTPS é obrigatório e a lista de redes liberadas é ignorada — com aviso', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config([
        'webhooks.destination.require_https' => false,
        'webhooks.destination.allowed_private_networks' => ['127.0.0.1/32'],
    ]);

    expect(destinationReason('http://hooks.example.com/hook'))->toBe('scheme_not_allowed')
        ->and(destinationReason('https://127.0.0.1/hook'))->toBe('private_address')
        ->and(app(DestinationPolicy::class)->warnings())->toHaveCount(2);
});

it('aceita IPv6 público e prefere IPv4 quando o nome tem os dois', function (): void {
    $this->dns->records['duplo.example.com'] = ['2606:2800:220:1:248:1893:25c8:1946', '93.184.215.14'];

    $destination = app(DestinationPolicy::class)->check('https://duplo.example.com/');

    expect($destination->address)->toBe('93.184.215.14')
        ->and(app(DestinationPolicy::class)->check('https://[2606:2800:220:1:248:1893:25c8:1946]:8443/x')->port)->toBe(8443);

    $this->dns->records['so-v6.example.com'] = ['2606:2800:220:1:248:1893:25c8:1946'];

    expect(app(DestinationPolicy::class)->check('https://so-v6.example.com/')->curlResolveEntry())
        ->toBe('so-v6.example.com:443:[2606:2800:220:1:248:1893:25c8:1946]');
});
