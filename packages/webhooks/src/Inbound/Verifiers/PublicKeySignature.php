<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use OpenSSLAsymmetricKey;
use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\Rejection;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * ASSINATURA COM CHAVE PÚBLICA (driver `public_key`): o provedor assina com
 * a chave privada dele (RSA ou EC) e publica a pública; aqui só a pública é
 * guardada — vazar a configuração não deixa ninguém forjar.
 *
 * - `header`: o cabeçalho da assinatura; `prefix` opcional;
 * - `encoding`: `base64` (padrão; aceita também base64url) ou `hex`;
 * - `algorithm`: `sha256` (padrão), `sha384` ou `sha512`;
 * - `public_keys`: as chaves vigentes, em PEM (o texto, ou `file://` com o
 *   caminho) — mais de uma na rotação;
 * - `signed_content`, `timestamp_header`, `timestamp_unit`, `id_header` e
 *   `tolerance`: como no driver `hmac` (padrão: só o corpo).
 *
 * Verifica com `openssl_verify` sobre o corpo cru.
 */
final readonly class PublicKeySignature implements Verifier
{
    private const ALGORITHMS = [
        'sha256' => OPENSSL_ALGO_SHA256,
        'sha384' => OPENSSL_ALGO_SHA384,
        'sha512' => OPENSSL_ALGO_SHA512,
    ];

    /**
     * @var list<OpenSSLAsymmetricKey>
     */
    private array $keys;

    private string $signedContent;

    /**
     * @param  array<array-key, mixed>|string|null  $publicKeys
     */
    public function __construct(
        private string $header,
        array|string|null $publicKeys,
        private string $algorithm = 'sha256',
        private string $encoding = 'base64',
        private string $prefix = '',
        private ?string $timestampHeader = null,
        private string $timestampUnit = 'seconds',
        private ?string $idHeader = null,
        ?string $signedContent = null,
        private int $tolerance = 300,
    ) {
        if (trim($header) === '') {
            throw new InvalidSourceConfiguration('public_key: informe o cabeçalho da assinatura (header).');
        }

        if (! array_key_exists($algorithm, self::ALGORITHMS)) {
            throw new InvalidSourceConfiguration("public_key: algoritmo não aceito: {$algorithm} (use sha256, sha384 ou sha512).");
        }

        SignatureTools::assertEncoding($encoding);

        $this->signedContent = $signedContent ?? ($timestampHeader !== null ? '{timestamp}.{body}' : '{body}');
        SignatureTools::assertTemplate($this->signedContent);

        if (str_contains($this->signedContent, '{timestamp}') && $timestampHeader === null) {
            throw new InvalidSourceConfiguration('public_key: o conteúdo assinado usa {timestamp}, mas não há timestamp_header.');
        }

        if (str_contains($this->signedContent, '{id}') && $idHeader === null) {
            throw new InvalidSourceConfiguration('public_key: o conteúdo assinado usa {id}, mas não há id_header.');
        }

        $keys = [];

        foreach (SignatureTools::secrets($publicKeys) as $pem) {
            // PEM numa variável de ambiente costuma vir com `\n` escrito.
            $pem = str_starts_with($pem, '-----BEGIN') ? str_replace('\\n', "\n", $pem) : $pem;
            $key = openssl_pkey_get_public($pem);

            if ($key === false) {
                while (openssl_error_string() !== false) {
                    // Esvazia a fila de erros do OpenSSL.
                }

                throw new InvalidSourceConfiguration('public_key: uma das chaves públicas não é PEM válido.');
            }

            $keys[] = $key;
        }

        $this->keys = $keys;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromConfig(array $options): self
    {
        $keys = $options['public_keys'] ?? $options['public_key'] ?? null;

        return new self(
            header: SignatureTools::string($options, 'header'),
            publicKeys: is_array($keys) || is_string($keys) ? $keys : null,
            algorithm: strtolower(SignatureTools::string($options, 'algorithm', 'sha256')),
            encoding: strtolower(SignatureTools::string($options, 'encoding', 'base64')),
            prefix: SignatureTools::string($options, 'prefix'),
            timestampHeader: SignatureTools::nullableString($options, 'timestamp_header'),
            timestampUnit: SignatureTools::string($options, 'timestamp_unit', 'seconds'),
            idHeader: SignatureTools::nullableString($options, 'id_header'),
            signedContent: SignatureTools::nullableString($options, 'signed_content'),
            tolerance: SignatureTools::tolerance($options),
        );
    }

    public function verify(InboundRequest $request): VerificationResult
    {
        if ($this->keys === []) {
            return VerificationResult::failed(Rejection::NotConfigured);
        }

        $value = $request->header($this->header);

        if ($value === null || trim($value) === '') {
            return VerificationResult::failed(Rejection::MissingSignature);
        }

        $parsed = SignatureTools::parseHeader($value, 'plain', $this->prefix);
        $signature = $parsed === null ? null : SignatureTools::decode($parsed['candidates'][0], $this->encoding);

        if ($signature === null) {
            return VerificationResult::failed(Rejection::MalformedSignature);
        }

        $timestamp = null;
        $signedTimestamp = null;

        if ($this->timestampHeader !== null) {
            $signedTimestamp = $request->header($this->timestampHeader);
            $timestamp = SignatureTools::timestamp($signedTimestamp, $this->timestampUnit);

            if ($timestamp === null) {
                return VerificationResult::failed(Rejection::MissingTimestamp);
            }

            $signedTimestamp = trim((string) $signedTimestamp);
        }

        $id = $this->idHeader !== null ? $request->header($this->idHeader) : null;

        if ($this->idHeader !== null && ($id === null || trim($id) === '')) {
            return VerificationResult::failed(Rejection::MissingEventId);
        }

        $content = SignatureTools::signedContent($this->signedContent, $request->body, $id, $signedTimestamp);

        if ($content === null) {
            return VerificationResult::failed(Rejection::MalformedSignature);
        }

        $matched = false;

        foreach ($this->keys as $key) {
            $matched = openssl_verify($content, $signature, $key, self::ALGORITHMS[$this->algorithm]) === 1 || $matched;
        }

        while (openssl_error_string() !== false) {
            // Assinatura ruim deixa erro na fila do OpenSSL: não vaza para a
            // próxima operação.
        }

        if (! $matched) {
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
        return ['header' => $this->header, 'algorithm' => $this->algorithm, 'keys' => count($this->keys)];
    }
}
