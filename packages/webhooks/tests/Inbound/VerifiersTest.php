<?php

declare(strict_types=1);

use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\Rejection;
use Twstec\Kit\Webhooks\Inbound\Verifiers\HmacSignature;
use Twstec\Kit\Webhooks\Inbound\Verifiers\PublicKeySignature;
use Twstec\Kit\Webhooks\Inbound\Verifiers\SharedSecretHeader;
use Twstec\Kit\Webhooks\Inbound\Verifiers\StandardWebhooks;
use Twstec\Kit\Webhooks\Inbound\Verifiers\VerifierFactory;

// =============================================================================
// OS VERIFICADORES, um a um: válido, inválido, sem cabeçalho, carimbo velho
// e futuro, segredo/chave rotacionado, e a configuração que não pode valer.
// Sempre sobre o corpo CRU — o corpo de teste tem espaços e acento que um
// JSON recodificado mudaria.
// =============================================================================

const CORPO_CRU = "{\"id\": \"evt_1\",  \"type\":\"pagamento.recebido\", \"nome\": \"Jos\u{e9}\"}\n";

describe('token em cabeçalho (header_token)', function (): void {
    $verifier = fn (): SharedSecretHeader => SharedSecretHeader::fromConfig(['header' => 'x-provedor-token', 'secrets' => [INBOUND_TOKEN, INBOUND_TOKEN_PREVIOUS]]);

    it('aceita o token atual e o anterior (rotação)', function () use ($verifier): void {
        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Provedor-Token' => INBOUND_TOKEN]))->passed)->toBeTrue()
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => INBOUND_TOKEN_PREVIOUS]))->passed)->toBeTrue();
    });

    it('recusa token errado, token com um caractere a mais e cabeçalho ausente', function () use ($verifier): void {
        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => 'tok_errado']))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => INBOUND_TOKEN.'x']))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => '']))->rejection)->toBe(Rejection::MissingSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU))->rejection)->toBe(Rejection::MissingSignature);
    });

    it('sem segredo configurado RECUSA tudo — até o cabeçalho vazio', function (): void {
        $semSegredo = SharedSecretHeader::fromConfig(['header' => 'x-provedor-token', 'secrets' => [null, '', '  ']]);

        expect($semSegredo->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => '']))->rejection)->toBe(Rejection::NotConfigured)
            ->and($semSegredo->verify(inboundRequest(CORPO_CRU, ['x-provedor-token' => 'qualquer']))->rejection)->toBe(Rejection::NotConfigured);
    });

    it('com prefixo (Authorization: Bearer …), exige o prefixo', function (): void {
        $bearer = SharedSecretHeader::fromConfig(['header' => 'Authorization', 'prefix' => 'Bearer ', 'secrets' => INBOUND_TOKEN]);

        expect($bearer->verify(inboundRequest(CORPO_CRU, ['Authorization' => 'Bearer '.INBOUND_TOKEN]))->passed)->toBeTrue()
            ->and($bearer->verify(inboundRequest(CORPO_CRU, ['Authorization' => INBOUND_TOKEN]))->rejection)->toBe(Rejection::MalformedSignature);
    });
});

describe('HMAC do corpo (hmac, formato plain)', function (): void {
    $verifier = fn (array $secrets = [INBOUND_HMAC_SECRET]): HmacSignature => HmacSignature::fromConfig(['header' => 'X-Hub-Signature-256', 'prefix' => 'sha256=', 'secrets' => $secrets]);
    $sign = fn (string $body, string $secret = INBOUND_HMAC_SECRET): string => 'sha256='.hash_hmac('sha256', $body, $secret);

    it('aceita a assinatura do corpo cru (hex, maiúsculas também)', function () use ($verifier, $sign): void {
        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => $sign(CORPO_CRU)]))->passed)->toBeTrue()
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => 'sha256='.strtoupper(hash_hmac('sha256', CORPO_CRU, INBOUND_HMAC_SECRET))]))->passed)->toBeTrue();
    });

    it('recusa o corpo recodificado (mesmo JSON, outros bytes), o corpo alterado e o segredo errado', function () use ($verifier, $sign): void {
        $recodificado = (string) json_encode(json_decode(CORPO_CRU, true));

        expect($recodificado)->not->toBe(CORPO_CRU)
            ->and($verifier()->verify(inboundRequest($recodificado, ['X-Hub-Signature-256' => $sign(CORPO_CRU)]))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU.' ', ['X-Hub-Signature-256' => $sign(CORPO_CRU)]))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => $sign(CORPO_CRU, 'outro')]))->rejection)->toBe(Rejection::InvalidSignature);
    });

    it('recusa sem cabeçalho e sem o prefixo', function () use ($verifier): void {
        expect($verifier()->verify(inboundRequest(CORPO_CRU))->rejection)->toBe(Rejection::MissingSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => hash_hmac('sha256', CORPO_CRU, INBOUND_HMAC_SECRET)]))->rejection)->toBe(Rejection::MalformedSignature);
    });

    it('com dois segredos vigentes, aceita a assinatura de qualquer um (rotação)', function () use ($verifier, $sign): void {
        $rotacao = $verifier([INBOUND_HMAC_SECRET, INBOUND_HMAC_SECRET_PREVIOUS]);

        expect($rotacao->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => $sign(CORPO_CRU, INBOUND_HMAC_SECRET_PREVIOUS)]))->passed)->toBeTrue()
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['X-Hub-Signature-256' => $sign(CORPO_CRU, INBOUND_HMAC_SECRET_PREVIOUS)]))->rejection)->toBe(Rejection::InvalidSignature);
    });

    it('base64, sha512 e segredo em base64 também se configuram', function (): void {
        $segredo = random_bytes(32);
        $verifier = HmacSignature::fromConfig(['header' => 'x-sig', 'algorithm' => 'sha512', 'encoding' => 'base64', 'secret_encoding' => 'base64', 'secrets' => base64_encode($segredo)]);

        expect($verifier->verify(inboundRequest(CORPO_CRU, ['x-sig' => base64_encode(hash_hmac('sha512', CORPO_CRU, $segredo, true))]))->passed)->toBeTrue()
            ->and($verifier->verify(inboundRequest(CORPO_CRU, ['x-sig' => base64_encode(hash_hmac('sha256', CORPO_CRU, $segredo, true))]))->rejection)->toBe(Rejection::InvalidSignature);
    });
});

describe('HMAC com carimbo de tempo (hmac, formato keyed: t=…,v1=…)', function (): void {
    $verifier = fn (int $tolerance = 300): HmacSignature => HmacSignature::fromConfig(['header' => 'Assinatura', 'format' => 'keyed', 'timestamp_key' => 't', 'secrets' => [INBOUND_HMAC_SECRET, INBOUND_HMAC_SECRET_PREVIOUS], 'tolerance' => $tolerance]);

    it('aceita dentro da janela e devolve o carimbo assinado', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $result = $verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => keyedSignature(CORPO_CRU, $agora - 299, INBOUND_HMAC_SECRET)], $agora));

        expect($result->passed)->toBeTrue()->and($result->timestamp)->toBe($agora - 299);
    });

    it('recusa carimbo VELHO e carimbo FUTURO além da janela — mesmo com a assinatura certa', function () use ($verifier): void {
        $agora = 1_800_000_000;

        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => keyedSignature(CORPO_CRU, $agora - 301, INBOUND_HMAC_SECRET)], $agora))->rejection)->toBe(Rejection::StaleTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => keyedSignature(CORPO_CRU, $agora + 301, INBOUND_HMAC_SECRET)], $agora))->rejection)->toBe(Rejection::FutureTimestamp)
            ->and($verifier(60)->verify(inboundRequest(CORPO_CRU, ['Assinatura' => keyedSignature(CORPO_CRU, $agora - 61, INBOUND_HMAC_SECRET)], $agora))->rejection)->toBe(Rejection::StaleTimestamp);
    });

    it('recusa carimbo trocado (assinado com outro), carimbo ausente e carimbo que não é número', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $assinada = hash_hmac('sha256', ($agora - 10).'.'.CORPO_CRU, INBOUND_HMAC_SECRET);

        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => "t={$agora},v1={$assinada}"], $agora))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => "v1={$assinada}"], $agora))->rejection)->toBe(Rejection::MissingTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => "t=agora,v1={$assinada}"], $agora))->rejection)->toBe(Rejection::MissingTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => 'lixo'], $agora))->rejection)->toBe(Rejection::MalformedSignature);
    });

    it('vários v1 no cabeçalho (o provedor rotacionando): basta um conferir', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $header = keyedSignature(CORPO_CRU, $agora, 'segredo-novo-que-ainda-nao-temos', INBOUND_HMAC_SECRET_PREVIOUS);

        expect($verifier()->verify(inboundRequest(CORPO_CRU, ['Assinatura' => $header], $agora))->passed)->toBeTrue();
    });

    it('carimbo em cabeçalho próprio, em milissegundos, assinado como veio', function (): void {
        $agora = 1_800_000_000;
        $ms = (string) (($agora - 5) * 1000);
        $verifier = HmacSignature::fromConfig(['header' => 'x-sig', 'timestamp_header' => 'x-ts', 'timestamp_unit' => 'milliseconds', 'signed_content' => '{timestamp}:{body}', 'secrets' => INBOUND_HMAC_SECRET]);
        $sig = hash_hmac('sha256', $ms.':'.CORPO_CRU, INBOUND_HMAC_SECRET);

        expect($verifier->verify(inboundRequest(CORPO_CRU, ['x-sig' => $sig, 'x-ts' => $ms], $agora))->passed)->toBeTrue()
            ->and($verifier->verify(inboundRequest(CORPO_CRU, ['x-sig' => $sig, 'x-ts' => (string) (($agora - 400) * 1000)], $agora))->rejection)->toBe(Rejection::InvalidSignature);
    });
});

describe('Standard Webhooks (standard_webhooks)', function (): void {
    $verifier = fn (string ...$secrets): StandardWebhooks => StandardWebhooks::fromConfig(['secrets' => $secrets === [] ? [standardSecret('atual')] : $secrets]);

    it('aceita a assinatura de {id}.{timestamp}.{corpo} e devolve o webhook-id assinado', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $result = $verifier()->verify(inboundRequest(CORPO_CRU, standardHeaders(CORPO_CRU, 'msg_2a', $agora, standardSecret('atual')), $agora));

        expect($result->passed)->toBeTrue()
            ->and($result->eventId)->toBe('msg_2a')
            ->and($result->timestamp)->toBe($agora);
    });

    it('recusa id trocado, carimbo velho, carimbo futuro e cabeçalhos ausentes', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $headers = standardHeaders(CORPO_CRU, 'msg_2a', $agora, standardSecret('atual'));

        expect($verifier()->verify(inboundRequest(CORPO_CRU, [...$headers, 'webhook-id' => 'msg_outro'], $agora))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, standardHeaders(CORPO_CRU, 'msg_2a', $agora - 400, standardSecret('atual')), $agora))->rejection)->toBe(Rejection::StaleTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, standardHeaders(CORPO_CRU, 'msg_2a', $agora + 400, standardSecret('atual')), $agora))->rejection)->toBe(Rejection::FutureTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, array_diff_key($headers, ['webhook-id' => 1]), $agora))->rejection)->toBe(Rejection::MissingEventId)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, array_diff_key($headers, ['webhook-timestamp' => 1]), $agora))->rejection)->toBe(Rejection::MissingTimestamp)
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, array_diff_key($headers, ['webhook-signature' => 1]), $agora))->rejection)->toBe(Rejection::MissingSignature);
    });

    it('rotação: duas assinaturas no cabeçalho, ou dois segredos vigentes aqui', function () use ($verifier): void {
        $agora = 1_800_000_000;
        $duasNoCabecalho = standardHeaders(CORPO_CRU, 'msg_3', $agora, standardSecret('novo'), standardSecret('atual'));
        $soAnterior = standardHeaders(CORPO_CRU, 'msg_3', $agora, standardSecret('anterior'));

        expect($verifier()->verify(inboundRequest(CORPO_CRU, $duasNoCabecalho, $agora))->passed)->toBeTrue()
            ->and($verifier()->verify(inboundRequest(CORPO_CRU, $soAnterior, $agora))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier(standardSecret('atual'), standardSecret('anterior'))->verify(inboundRequest(CORPO_CRU, $soAnterior, $agora))->passed)->toBeTrue();
    });
});

describe('chave pública (public_key, RSA)', function (): void {
    it('aceita a assinatura RSA-SHA256 do corpo cru e recusa a de outro corpo', function (): void {
        ['private' => $privada, 'public' => $publica] = rsaKeyPair();
        $verifier = PublicKeySignature::fromConfig(['header' => 'x-webhook-signature', 'public_keys' => [$publica]]);

        expect($verifier->verify(inboundRequest(CORPO_CRU, ['x-webhook-signature' => rsaSign(CORPO_CRU, $privada)]))->passed)->toBeTrue()
            ->and($verifier->verify(inboundRequest(CORPO_CRU, ['x-webhook-signature' => rsaSign(CORPO_CRU.'x', $privada)]))->rejection)->toBe(Rejection::InvalidSignature)
            ->and($verifier->verify(inboundRequest(CORPO_CRU))->rejection)->toBe(Rejection::MissingSignature)
            ->and($verifier->verify(inboundRequest(CORPO_CRU, ['x-webhook-signature' => '%%%não-é-base64%%%']))->rejection)->toBe(Rejection::MalformedSignature);
    });

    it('rotação: duas chaves públicas vigentes; a de outra chave privada não passa', function (): void {
        $atual = rsaKeyPair();
        $anterior = rsaKeyPair();
        $estranha = rsaKeyPair();
        $verifier = PublicKeySignature::fromConfig(['header' => 'x-webhook-signature', 'public_keys' => [$atual['public'], $anterior['public']]]);

        expect($verifier->verify(inboundRequest(CORPO_CRU, ['x-webhook-signature' => rsaSign(CORPO_CRU, $anterior['private'])]))->passed)->toBeTrue()
            ->and($verifier->verify(inboundRequest(CORPO_CRU, ['x-webhook-signature' => rsaSign(CORPO_CRU, $estranha['private'])]))->rejection)->toBe(Rejection::InvalidSignature);
    });

    it('com carimbo de tempo assinado, a janela vale; o PEM aceita `\n` escrito (variável de ambiente)', function (): void {
        ['private' => $privada, 'public' => $publica] = rsaKeyPair();
        $agora = 1_800_000_000;
        $verifier = PublicKeySignature::fromConfig(['header' => 'x-sig', 'timestamp_header' => 'x-ts', 'public_keys' => str_replace("\n", '\n', $publica)]);
        $assinar = fn (int $t): array => ['x-sig' => rsaSign($t.'.'.CORPO_CRU, $privada), 'x-ts' => (string) $t];

        expect($verifier->verify(inboundRequest(CORPO_CRU, $assinar($agora - 10), $agora))->passed)->toBeTrue()
            ->and($verifier->verify(inboundRequest(CORPO_CRU, $assinar($agora - 301), $agora))->rejection)->toBe(Rejection::StaleTimestamp)
            ->and($verifier->verify(inboundRequest(CORPO_CRU, $assinar($agora + 301), $agora))->rejection)->toBe(Rejection::FutureTimestamp);
    });

    it('sem chave configurada recusa; PEM inválido é erro de configuração', function (): void {
        expect(PublicKeySignature::fromConfig(['header' => 'x-sig', 'public_keys' => []])->verify(inboundRequest(CORPO_CRU, ['x-sig' => 'YQ==']))->rejection)->toBe(Rejection::NotConfigured)
            ->and(fn () => PublicKeySignature::fromConfig(['header' => 'x-sig', 'public_keys' => ['-----BEGIN PUBLIC KEY-----\nlixo\n-----END PUBLIC KEY-----']]))->toThrow(InvalidSourceConfiguration::class);
    });
});

describe('configuração que não pode valer', function (): void {
    it('conteúdo assinado sem {body}, {timestamp} sem fonte de carimbo, {id} sem cabeçalho, algoritmo e driver desconhecidos', function (): void {
        expect(fn () => HmacSignature::fromConfig(['header' => 'x', 'secrets' => 's', 'signed_content' => '{timestamp}']))->toThrow(InvalidSourceConfiguration::class, '{body}')
            ->and(fn () => HmacSignature::fromConfig(['header' => 'x', 'secrets' => 's', 'signed_content' => '{timestamp}.{body}']))->toThrow(InvalidSourceConfiguration::class, 'timestamp')
            ->and(fn () => HmacSignature::fromConfig(['header' => 'x', 'secrets' => 's', 'signed_content' => '{id}.{body}']))->toThrow(InvalidSourceConfiguration::class, 'id_header')
            ->and(fn () => HmacSignature::fromConfig(['header' => 'x', 'secrets' => 's', 'algorithm' => 'md5']))->toThrow(InvalidSourceConfiguration::class, 'md5')
            ->and(fn () => HmacSignature::fromConfig(['header' => '', 'secrets' => 's']))->toThrow(InvalidSourceConfiguration::class)
            ->and(fn () => VerifierFactory::make(['driver' => 'inventado']))->toThrow(InvalidSourceConfiguration::class, 'inventado')
            ->and(fn () => VerifierFactory::make(['driver' => stdClass::class]))->toThrow(InvalidSourceConfiguration::class)
            ->and(fn () => VerifierFactory::make([]))->toThrow(InvalidSourceConfiguration::class);
    });

    it('os drivers do pacote têm nome genérico — nenhum provedor no núcleo', function (): void {
        expect(array_keys(VerifierFactory::DRIVERS))->toBe(['header_token', 'hmac', 'standard_webhooks', 'public_key']);
    });

    it('o verificador não imprime segredo num dump', function (): void {
        $verifier = SharedSecretHeader::fromConfig(['header' => 'x-provedor-token', 'secrets' => [INBOUND_TOKEN]]);

        expect(print_r($verifier, true))->not->toContain(INBOUND_TOKEN)
            ->and(print_r(HmacSignature::fromConfig(['header' => 'x', 'secrets' => INBOUND_HMAC_SECRET]), true))->not->toContain(INBOUND_HMAC_SECRET)
            ->and(print_r(inboundRequest('corpo-secreto', ['x-provedor-token' => INBOUND_TOKEN]), true))->not->toContain('corpo-secreto')->not->toContain(INBOUND_TOKEN);
    });
});
