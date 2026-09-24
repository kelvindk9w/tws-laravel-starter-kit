<?php

declare(strict_types=1);

namespace Twstec\Kit\Accounts\ApiKeys\Support;

/**
 * Hash da chave SECRETA (sk_) — só o hash vai ao banco; a secreta é exibida uma vez.
 *
 * DECISÃO: HMAC-SHA256 com pepper (padrão Sanctum, que usa SHA-256 puro).
 * Justificativa:
 * - A sk_ tem ~285 bits de entropia aleatória criptográfica. KDFs lentas
 *   (Argon2id/bcrypt) existem para proteger segredos de BAIXA entropia
 *   (senhas humanas) contra força bruta; para um segredo aleatório desse
 *   tamanho, força bruta é inviável e a KDF lenta só adicionaria latência
 *   obrigatória a CADA request da API.
 * - O pepper (segredo fora do banco, via .env) garante que um vazamento
 *   SOMENTE do banco não permita computar/verificar hashes.
 * - A comparação é SEMPRE timing-safe: hash_equals() — timing attack em
 *   comparação de segredos é vetor real.
 */
final class ApiKeyHasher
{
    /**
     * Hash determinístico da secreta (é o que vai para secret_hash).
     */
    public function hash(string $plainSecret): string
    {
        return hash_hmac('sha256', $plainSecret, $this->pepper());
    }

    /**
     * Verificação timing-safe (hash_equals — NUNCA ===).
     */
    public function verify(string $plainSecret, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($plainSecret));
    }

    /**
     * Pepper do HMAC: segredo dedicado (API_KEYS_HASH_PEPPER) com fallback
     * para a APP_KEY — nunca hardcoded.
     */
    private function pepper(): string
    {
        return (string) config('api_keys.hash_pepper');
    }
}
