<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Services;

use Illuminate\Support\Facades\Hash;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\VerificationChannel;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Enums\VerificationResult;
use Twstec\Kit\Auth\Models\VerificationCode;
use Twstec\Kit\Auth\Verification\VerificationChannelManager;

/**
 * O MOTOR dos códigos de verificação de 6 dígitos, em um lugar só.
 *
 * Quem usa: a confirmação de ação sensível (SensitiveActionService) e o
 * segundo fator do login (TwoFactorLogin). Cada fluxo tem a sua FINALIDADE
 * (VerificationPurpose) e os códigos de finalidades diferentes não se
 * misturam: um código pedido para autorizar uma chave de API não abre uma
 * sessão, e vice-versa.
 *
 * Invariantes (valem para todo fluxo que passe por aqui):
 * - no banco, SÓ o hash do código (Argon2id); em claro ele existe apenas no
 *   job criptografado da fila e no e-mail;
 * - validade curta (AUTH_VERIFICATION_CODE_TTL_MINUTES);
 * - código novo invalida os anteriores da mesma finalidade;
 * - tentativas limitadas por código (AUTH_VERIFICATION_CODE_MAX_ATTEMPTS) —
 *   e a tentativa é RESERVADA no banco antes de o hash ser conferido, com um
 *   UPDATE condicional. Assim, nem uma rajada de requisições simultâneas
 *   consegue conferir o mesmo código mais vezes que o limite;
 * - USO ÚNICO: o consumo também é um UPDATE condicional (`consumed_at IS
 *   NULL`). Duas requisições com o código certo ao mesmo tempo: só uma vence.
 * - comparação segura: `Hash::check` (tempo constante do password_verify).
 */
final class VerificationCodes
{
    public function __construct(
        private readonly VerificationChannelManager $channels,
    ) {}

    /**
     * Gera um código novo, invalida os anteriores da finalidade e envia pelo
     * canal. Quem chama decide antes se o cooldown permite
     * (cooldownRemaining()).
     */
    public function issue(AuthUser $user, VerificationPurpose $purpose, ?VerificationChannel $channel = null): VerificationCode
    {
        $channel ??= $this->channels->defaultChannel();

        $this->invalidate($user, $purpose);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        /** @var VerificationCode $record */
        $record = VerificationCode::query()->create([
            'user_id' => $user->id,
            'channel' => $channel,
            'purpose' => $purpose,
            // SOMENTE o hash — nunca o código em claro.
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        $this->channels->driver($channel)->send($user, $code, $purpose);

        return $record;
    }

    /**
     * Confere o código contra o último código ativo da finalidade.
     */
    public function verify(AuthUser $user, VerificationPurpose $purpose, #[\SensitiveParameter] string $code): VerificationResult
    {
        $record = $this->latestActive($user, $purpose);

        if ($record === null || $record->isExpired()) {
            return VerificationResult::Expired;
        }

        // Reserva a tentativa ANTES de conferir: só passa daqui quem conseguiu
        // incrementar um código ainda vivo e abaixo do limite.
        $reserved = VerificationCode::query()
            ->whereKey($record->id)
            ->whereNull('consumed_at')
            ->where('attempts', '<', $this->maxAttempts())
            ->increment('attempts');

        if ($reserved === 0) {
            return VerificationResult::Expired;
        }

        $matches = preg_match('/^\d{6}$/', $code) === 1 && Hash::check($code, $record->code_hash);

        if (! $matches) {
            // Tentativas esgotadas: o código morre (força um envio novo).
            VerificationCode::query()
                ->whereKey($record->id)
                ->whereNull('consumed_at')
                ->where('attempts', '>=', $this->maxAttempts())
                ->update(['consumed_at' => now()]);

            return VerificationResult::Invalid;
        }

        $consumed = VerificationCode::query()
            ->whereKey($record->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return $consumed === 1 ? VerificationResult::Valid : VerificationResult::Expired;
    }

    /**
     * Invalida todos os códigos ainda vivos da finalidade (novo envio,
     * cancelamento do fluxo).
     */
    public function invalidate(AuthUser $user, VerificationPurpose $purpose): void
    {
        VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * Segundos que faltam para poder enviar outro código (0 = pode enviar).
     */
    public function cooldownRemaining(AuthUser $user, VerificationPurpose $purpose, ?VerificationChannel $channel = null): int
    {
        $channel ??= $this->channels->defaultChannel();

        /** @var VerificationCode|null $latest */
        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->where('channel', $channel->value)
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($latest === null) {
            return 0;
        }

        $elapsed = (int) $latest->created_at?->diffInSeconds(now());

        return max(0, $this->resendCooldownSeconds() - $elapsed);
    }

    /**
     * Existe um código vivo (não usado, não vencido) desta finalidade?
     */
    public function hasActive(AuthUser $user, VerificationPurpose $purpose): bool
    {
        $record = $this->latestActive($user, $purpose);

        return $record !== null && ! $record->isExpired();
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('auth.verification.code_ttl_minutes', 10));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('auth.verification.max_attempts', 5));
    }

    public function resendCooldownSeconds(): int
    {
        return max(0, (int) config('auth.verification.resend_cooldown_seconds', 60));
    }

    private function latestActive(AuthUser $user, VerificationPurpose $purpose): ?VerificationCode
    {
        /** @var VerificationCode|null */
        return VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
