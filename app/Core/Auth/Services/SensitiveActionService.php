<?php

declare(strict_types=1);

namespace App\Core\Auth\Services;

use App\Core\Auth\Enums\VerificationChannel;
use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Models\SensitiveActionToken;
use App\Core\Auth\Models\User;
use App\Core\Auth\Models\VerificationCode;
use App\Core\Auth\Verification\VerificationChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fluxo de confirmação de AÇÃO SENSÍVEL (ADR-006/010):
 *
 *   1. sendCode()     — valida a SENHA DE TRANSAÇÃO (hash separado) e envia
 *                       um código de 6 dígitos pelo canal configurado (e-mail).
 *   2. confirmCode()  — valida o código (expiração + máx. tentativas + hash)
 *                       e emite um TOKEN DE AÇÃO SENSÍVEL de curta duração.
 *   3. validateToken()— o middleware `sensitive.token` valida (e consome) o
 *                       token antes da operação sensível (saque, rotação de
 *                       chave de API...). USO ÚNICO.
 *
 * Invariantes: código e token NUNCA em plaintext no banco (somente hash),
 * sempre com expiração; reenvio com cooldown; tentativas limitadas.
 */
final class SensitiveActionService
{
    public function __construct(
        private readonly VerificationChannelManager $channels,
    ) {}

    /**
     * Passo 1: valida a senha de transação e dispara o código de verificação.
     *
     * @throws ValidationException Senha incorreta/não definida ou cooldown ativo.
     */
    public function sendCode(User $user, string $transactionPassword, ?VerificationChannel $channel = null): VerificationCode
    {
        if (! $user->hasTransactionPassword() || ! Hash::check($transactionPassword, (string) $user->transaction_password)) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.transaction_password.invalid'),
            ]);
        }

        $channel ??= $this->channels->defaultChannel();

        $this->ensureResendCooldown($user, $channel);

        // Um código novo invalida os ativos anteriores da mesma finalidade.
        VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationPurpose::SensitiveAction->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        /** @var VerificationCode $record */
        $record = VerificationCode::query()->create([
            'user_id' => $user->id,
            'channel' => $channel,
            'purpose' => VerificationPurpose::SensitiveAction,
            // SOMENTE o hash — nunca o código em claro (checklist 24).
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($this->codeTtlMinutes()),
        ]);

        $this->channels->driver($channel)->send($user, $code, VerificationPurpose::SensitiveAction);

        return $record;
    }

    /**
     * Passo 2: valida o código e emite o token de ação sensível.
     * Retorna o token EM CLARO — exibido uma única vez (no banco, só o hash).
     *
     * @return array{token: string, expires_at: Carbon}
     *
     * @throws ValidationException Código inválido, expirado ou tentativas esgotadas.
     */
    public function confirmCode(User $user, string $code): array
    {
        $record = $this->latestCode($user);

        if ($record === null || $record->isExpired()) {
            throw ValidationException::withMessages([
                'code' => __('auth.verification_code.expired'),
            ]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            if ($record->attempts >= $this->maxAttempts()) {
                // Tentativas esgotadas: o código morre (força novo envio).
                $record->update(['consumed_at' => now()]);
            }

            throw ValidationException::withMessages([
                'code' => __('auth.verification_code.invalid'),
            ]);
        }

        $record->update(['consumed_at' => now()]);

        return $this->issueToken($user);
    }

    /**
     * Passo 3: valida o token de ação sensível (uso único — consome ao validar).
     */
    public function validateToken(User $user, string $plainToken): bool
    {
        /** @var SensitiveActionToken|null $token */
        $token = SensitiveActionToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return false;
        }

        $token->update(['consumed_at' => now()]);

        return true;
    }

    /**
     * Segundos restantes de cooldown de reenvio (0 = pode reenviar).
     */
    public function resendCooldownRemaining(User $user, ?VerificationChannel $channel = null): int
    {
        $channel ??= $this->channels->defaultChannel();

        /** @var VerificationCode|null $latest */
        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationPurpose::SensitiveAction->value)
            ->where('channel', $channel->value)
            ->latest('created_at')
            ->first();

        if ($latest === null) {
            return 0;
        }

        $elapsed = $latest->created_at->diffInSeconds(now());

        return max(0, $this->resendCooldownSeconds() - (int) $elapsed);
    }

    /**
     * @throws ValidationException Cooldown de reenvio ainda ativo.
     */
    private function ensureResendCooldown(User $user, VerificationChannel $channel): void
    {
        $remaining = $this->resendCooldownRemaining($user, $channel);

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.verification_code.resend_cooldown', ['seconds' => $remaining]),
            ]);
        }
    }

    /**
     * Último código ativo (não consumido) da finalidade de ação sensível.
     */
    private function latestCode(User $user): ?VerificationCode
    {
        /** @var VerificationCode|null */
        return VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationPurpose::SensitiveAction->value)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();
    }

    /**
     * Emite o token de ação sensível: aleatório criptograficamente seguro,
     * somente o hash (SHA-256) persistido, expiração curta obrigatória.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    private function issueToken(User $user): array
    {
        $plainToken = Str::random(64);

        $expiresAt = now()->addMinutes((int) config('auth.sensitive_action.token_ttl_minutes', 10));

        SensitiveActionToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plainToken, 'expires_at' => $expiresAt];
    }

    private function codeTtlMinutes(): int
    {
        return (int) config('auth.verification.code_ttl_minutes', 10);
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.verification.max_attempts', 5);
    }

    private function resendCooldownSeconds(): int
    {
        return (int) config('auth.verification.resend_cooldown_seconds', 60);
    }
}
