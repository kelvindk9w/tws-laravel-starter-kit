<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundWebhookHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;
use Twstec\Kit\Webhooks\Inbound\InboundSource;
use Twstec\Kit\Webhooks\Inbound\InboundWebhooks;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\JsonEventParser;
use Twstec\Kit\Webhooks\Inbound\Verifiers\SharedSecretHeader;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// COMO O APLICATIVO REGISTRA UMA FONTE: pela configuração ou pelo código; o
// caminho da rota; desligar; e a configuração que não pode valer.
// =============================================================================

beforeEach(function (): void {
    RecordingInboundHandler::reset();
    config(['webhooks.inbound_sources' => []]);
});

it('registro pelo código, com verificador e handler montados no provider', function (): void {
    $recebidos = new ArrayObject;

    InboundWebhooks::register(new InboundSource(
        name: 'pelo-codigo',
        verifier: new SharedSecretHeader('x-chave', [INBOUND_TOKEN]),
        handler: new class($recebidos) implements InboundWebhookHandler
        {
            /**
             * @param  ArrayObject<int, InboundEvent>  $recebidos
             */
            public function __construct(private ArrayObject $recebidos) {}

            public function handle(InboundEvent $event): void
            {
                $this->recebidos[] = $event;
            }
        },
        parser: new JsonEventParser(idPath: 'id', typePath: 'event'),
    ));

    postInbound('pelo-codigo', inboundBody('evt_codigo'), ['x-chave' => INBOUND_TOKEN])->assertStatus(202);
    postInbound('pelo-codigo', inboundBody('evt_codigo'), ['x-chave' => 'outra'])->assertStatus(401);

    expect($recebidos)->toHaveCount(1)
        ->and($recebidos[0]->id)->toBe('evt_codigo')
        ->and($recebidos[0]->type)->toBe('PAYMENT_RECEIVED')
        ->and(InboundWebhooks::source('pelo-codigo'))->toBeInstanceOf(InboundSource::class)
        ->and(InboundWebhooks::source('nao-existe'))->toBeNull();
});

it('a fonte da configuração só tem texto, números e listas — vale com o config:cache', function (): void {
    inboundSources();

    $valores = [];
    $fontes = (array) config('webhooks.inbound_sources');
    array_walk_recursive($fontes, function (mixed $value) use (&$valores): void {
        $valores[] = get_debug_type($value);
    });

    expect(array_unique($valores))->each->toBeIn(['string', 'int', 'bool', 'null'])
        ->and(var_export(config('webhooks.inbound_sources'), true))->toContain('RecordingInboundHandler');
});

it('nome, verificador, handler, parser e allowlist inválidos são erro de configuração', function (array $config, string $trecho, string $name = 'fonte'): void {
    expect(fn () => InboundSource::fromConfig($name, $config))->toThrow(InvalidSourceConfiguration::class, $trecho);
})->with([
    'nome com maiúscula' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'], 'handler' => RecordingInboundHandler::class], 'Nome de fonte', 'Fonte'],
    'sem verifier' => [['handler' => RecordingInboundHandler::class], 'verifier'],
    'sem handler' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's']], 'handler'],
    'handler errado' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'], 'handler' => stdClass::class], 'handler'],
    'parser errado' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'], 'handler' => RecordingInboundHandler::class, 'parser' => stdClass::class], 'parser'],
    'faixa IPv4 inválida' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'], 'handler' => RecordingInboundHandler::class, 'allowed_ips' => ['10.0.0.0/33']], '10.0.0.0/33'],
    'faixa IPv6 inválida' => [['verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'], 'handler' => RecordingInboundHandler::class, 'allowed_ips' => ['2001:db8::/129']], '2001:db8::/129'],
]);

it('a allowlist aceita IP exato e CIDR, IPv4 e IPv6, e lista separada por vírgula', function (): void {
    $source = InboundSource::fromConfig('fonte', [
        'verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'],
        'handler' => RecordingInboundHandler::class,
        'allowed_ips' => '203.0.113.5, 198.51.100.0/24 ,2001:db8::/32',
    ]);

    expect($source->allowedIps)->toBe(['203.0.113.5', '198.51.100.0/24', '2001:db8::/32'])
        ->and($source->permits('198.51.100.200'))->toBeTrue()
        ->and($source->permits('2001:db8:1::1'))->toBeTrue()
        ->and($source->permits('203.0.113.6'))->toBeFalse()
        ->and($source->permits(null))->toBeFalse();
});

it('caminho configurável: a rota e o caminho de corpo assinado acompanham', function (): void {
    $this->bootWith(['webhooks.inbound.path' => '/integracoes/entrada/']);
    Queue::fake();
    inboundSources();

    expect(Route::getRoutes()->getByName('webhooks.inbound')?->uri())->toBe('integracoes/entrada/{source}')
        ->and(config('security.validation.signed_body_paths'))->toContain('integracoes/entrada/*')
        ->and(config('security.validation.signed_body_paths'))->not->toContain('webhooks/inbound/*');

    $this->call('POST', '/integracoes/entrada/token', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PROVEDOR_TOKEN' => INBOUND_TOKEN], inboundBody())->assertStatus(202);
});

it('caminho com curinga não registra a rota (nem desliga o filtro de ataques num caminho largo)', function (): void {
    $this->bootWith(['webhooks.inbound.path' => 'webhooks/*']);

    expect(Route::getRoutes()->getByName('webhooks.inbound'))->toBeNull()
        ->and(config('security.validation.signed_body_paths'))->not->toContain('webhooks/*/*');
});

it('desligado (`webhooks.inbound.enabled = false`): sem rota e sem caminho de corpo assinado', function (): void {
    $this->bootWith(['webhooks.inbound.enabled' => false]);
    inboundSources();

    expect(Route::getRoutes()->getByName('webhooks.inbound'))->toBeNull()
        ->and(config('security.validation.signed_body_paths'))->not->toContain('webhooks/inbound/*');

    postInbound('token', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN])->assertNotFound();
});

it('o caminho entra nos caminhos de corpo assinado mesmo quando o aplicativo escreveu a chave `validation` dele', function (): void {
    $this->bootWith(['security.validation' => ['signed_body_paths' => ['webhooks/whatsapp']]]);

    expect(config('security.validation.signed_body_paths'))->toContain('webhooks/whatsapp', 'webhooks/inbound/*');
});
