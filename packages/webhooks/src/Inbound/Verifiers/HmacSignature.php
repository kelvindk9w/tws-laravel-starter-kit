<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\Rejection;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * ASSINATURA HMAC (driver `hmac`), configurável para servir à maioria dos
 * provedores sem nomear nenhum:
 *
 * - `header`: o cabeçalho da assinatura;
 * - `format`: `plain` (o valor inteiro, depois do `prefix` — ex.:
 *   `sha256=<hex>`), `keyed` (pares `t=…,v1=…`, todas as `signature_key`
 *   conferidas, o carimbo em `timestamp_key`) ou `versioned` (entradas
 *   `v1,<assinatura>` separadas por espaço);
 * - `algorithm`: `sha256` (padrão), `sha384`, `sha512` ou `sha1` (só para
 *   provedor legado que não oferece outro);
 * - `encoding`: `hex` (padrão) ou `base64`;
 * - `signed_content`: o modelo do conteúdo assinado, com `{body}`
 *   obrigatório e, se o esquema assina, `{timestamp}` e `{id}` — padrão
 *   `{body}`, ou `{timestamp}.{body}` quando há carimbo de tempo;
 * - `timestamp_header` / `timestamp_key` e `timestamp_unit` (`seconds` ou
 *   `milliseconds`): de onde vem o carimbo e em que unidade; com ele, a
 *   janela `tolerance` (padrão `webhooks.inbound.tolerance_seconds`) vale
 *   para os dois lados (velho e futuro);
 * - `id_header`: o cabeçalho do id do evento, quando o esquema o assina;
 * - `secrets`: os segredos vigentes (o atual e o anterior na rotação);
 *   `secret_prefix` (tirado antes de usar — ex.: `whsec_`) e
 *   `secret_encoding` (`raw` ou `base64`).
 *
 * A assinatura é conferida sobre o CORPO CRU, em tempo constante, contra
 * cada segredo vigente; depois, a janela de tempo.
 */
final readonly class HmacSignature implements Verifier
{
    private const ALGORITHMS = ['sha1', 'sha256', 'sha384', 'sha512'];

    /**
     * @var list<string>
     */
    private array $secrets;

    private string $signedContent;

    /**
     * @param  array<array-key, mixed>|string|null  $secrets
     */
    public function __construct(
        private string $header,
        #[\SensitiveParameter] array|string|null $secrets,
        private string $algorithm = 'sha256',
        private string $encoding = 'hex',
        private string $format = 'plain',
        private string $prefix = '',
        private string $signatureKey = 'v1',
        private ?string $timestampKey = null,
        private ?string $timestampHeader = null,
        private string $timestampUnit = 'seconds',
        private ?string $idHeader = null,
        ?string $signedContent = null,
        private int $tolerance = 300,
        string $secretPrefix = '',
        string $secretEncoding = 'raw',
    ) {
        if (trim($header) === '') {
            throw new InvalidSourceConfiguration('hmac: informe o cabeçalho da assinatura (header).');
        }

        if (! in_array($algorithm, self::ALGORITHMS, true)) {
            throw new InvalidSourceConfiguration("hmac: algoritmo não aceito: {$algorithm} (use sha256, sha384, sha512 ou sha1).");
        }

        if (! in_array($format, ['plain', 'keyed', 'versioned'], true)) {
            throw new InvalidSourceConfiguration("hmac: formato de cabeçalho desconhecido: {$format} (use plain, keyed ou versioned).");
        }

        if (! in_array($timestampUnit, ['seconds', 'milliseconds'], true)) {
            throw new InvalidSourceConfiguration("hmac: unidade do carimbo desconhecida: {$timestampUnit}.");
        }

        if (! in_array($secretEncoding, ['raw', 'base64'], true)) {
            throw new InvalidSourceConfiguration("hmac: codificação de segredo desconhecida: {$secretEncoding} (use raw ou base64).");
        }

        SignatureTools::assertEncoding($encoding);

        $hasTimestamp = $timestampHeader !== null || ($format === 'keyed' && $timestampKey !== null);
        $this->signedContent = $signedContent ?? ($hasTimestamp ? '{timestamp}.{body}' : '{body}');
        SignatureTools::assertTemplate($this->signedContent);

        if (str_contains($this->signedContent, '{timestamp}') && ! $hasTimestamp) {
            throw new InvalidSourceConfiguration('hmac: o conteúdo assinado usa {timestamp}, mas não há timestamp_header (nem timestamp_key no formato keyed).');
        }

        if (str_contains($this->signedContent, '{id}') && $idHeader === null) {
            throw new InvalidSourceConfiguration('hmac: o conteúdo assinado usa {id}, mas não há id_header.');
        }

        $keys = [];

        foreach (SignatureTools::secrets($secrets) as $secret) {
            if ($secretPrefix !== '' && str_starts_with($secret, $secretPrefix)) {
                $secret = substr($secret, strlen($secretPrefix));
            }

            if ($secretEncoding === 'base64') {
                $decoded = base64_decode($secret, true);

                if ($decoded === false || $decoded === '') {
                    throw new InvalidSourceConfiguration('hmac: um dos segredos não é base64 válido (secret_encoding = base64).');
                }

                $secret = $decoded;
            }

            $keys[] = $secret;
        }

        $this->secrets = $keys;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromConfig(array $options): self
    {
        $secrets = $options['secrets'] ?? $options['secret'] ?? null;

        return new self(
            header: SignatureTools::string($options, 'header'),
            secrets: is_array($secrets) || is_string($secrets) ? $secrets : null,
            algorithm: strtolower(SignatureTools::string($options, 'algorithm', 'sha256')),
            encoding: strtolower(SignatureTools::string($options, 'encoding', 'hex')),
            format: SignatureTools::string($options, 'format', 'plain'),
            prefix: SignatureTools::string($options, 'prefix'),
            signatureKey: SignatureTools::string($options, 'signature_key', 'v1'),
            timestampKey: SignatureTools::nullableString($options, 'timestamp_key'),
            timestampHeader: SignatureTools::nullableString($options, 'timestamp_header'),
            timestampUnit: SignatureTools::string($options, 'timestamp_unit', 'seconds'),
            idHeader: SignatureTools::nullableString($options, 'id_header'),
            signedContent: SignatureTools::nullableString($options, 'signed_content'),
            tolerance: SignatureTools::tolerance($options),
            secretPrefix: SignatureTools::string($options, 'secret_prefix'),
            secretEncoding: SignatureTools::string($options, 'secret_encoding', 'raw'),
        );
    }

    public function verify(InboundRequest $request): VerificationResult
    {
        if ($this->secrets === []) {
            return VerificationResult::failed(Rejection::NotConfigured);
        }

        $value = $request->header($this->header);

        if ($value === null || trim($value) === '') {
            return VerificationResult::failed(Rejection::MissingSignature);
        }

        $parsed = SignatureTools::parseHeader($value, $this->format, $this->prefix, $this->signatureKey, $this->format === 'keyed' ? $this->timestampKey : null);

        if ($parsed === null) {
            return VerificationResult::failed(Rejection::MalformedSignature);
        }

        $timestamp = null;
        $signedTimestamp = null;

        if ($this->timestampHeader !== null || ($this->format === 'keyed' && $this->timestampKey !== null)) {
            $raw = $this->timestampHeader !== null ? $request->header($this->timestampHeader) : $parsed['timestamp'];
            $timestamp = SignatureTools::timestamp($raw, $this->timestampUnit);

            if ($timestamp === null) {
                return VerificationResult::failed(Rejection::MissingTimestamp);
            }

            // O carimbo assinado é o texto como VEIO (na unidade do provedor).
            $signedTimestamp = trim((string) $raw);
        }

        $id = $this->idHeader !== null ? $request->header($this->idHeader) : null;

        if ($this->idHeader !== null && ($id === null || trim($id) === '')) {
            return VerificationResult::failed(Rejection::MissingEventId);
        }

        $content = SignatureTools::signedContent($this->signedContent, $request->body, $id, $signedTimestamp);

        if ($content === null) {
            return VerificationResult::failed(Rejection::MalformedSignature);
        }

        $expected = [];

        foreach ($this->secrets as $secret) {
            $expected[] = SignatureTools::encode(hash_hmac($this->algorithm, $content, $secret, true), $this->encoding);
        }

        if (! SignatureTools::matchesAny($expected, $parsed['candidates'], $this->encoding)) {
            return VerificationResult::failed(Rejection::InvalidSignature);
        }

        if ($timestamp !== null && ($rejection = SignatureTools::window($timestamp, $request->receivedAt, $this->tolerance)) !== null) {
            return VerificationResult::failed($rejection);
        }

        return VerificationResult::passed($id, $timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['header' => $this->header, 'algorithm' => $this->algorithm, 'format' => $this->format, 'secrets' => count($this->secrets)];
    }
}
