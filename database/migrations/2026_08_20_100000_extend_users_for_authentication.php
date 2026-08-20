<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// Fase 3 — Autenticação (ADR-006/010).
//
// - users: identificadores externos (uuid + codigo_publico USR-xxxx — o `id`
//   interno NUNCA é exposto), senha de transação (hash SEPARADO da senha de
//   login) e status da conta.
// - `name` vira TEXT: o cast `encrypted` (AES-256-GCM da APP_KEY — checklist
//   item 12) expande o valor armazenado; VARCHAR(255) seria insuficiente.
// - verification_codes: códigos de verificação (2FA por e-mail e futuros
//   canais TOTP/WhatsApp). SEMPRE com hash + expiração — nunca plaintext.
// - sensitive_action_tokens: token de ação sensível, de curta duração e uso
//   único, emitido após senha de transação + código válido.
// =============================================================================
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identificadores externos (ADR-010) — o `id` nunca sai do banco.
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('codigo_publico', 32)->nullable()->unique()->after('uuid');

            // Dado pessoal criptografado em repouso (checklist 12) → TEXT.
            $table->text('name')->change();

            // Senha de transação: hash separado da senha de login (ADR-006).
            $table->string('transaction_password')->nullable()->after('password');
            $table->timestamp('transaction_password_set_at')->nullable()->after('transaction_password');

            // Status da conta (active/blocked/pending) — deny-by-default no login.
            $table->string('status', 20)->default('active')->after('remember_token');
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Canal (email hoje; totp/whatsapp futuros) e finalidade do código.
            $table->string('channel', 20);
            $table->string('purpose', 40);
            // NUNCA plaintext: somente o hash do código (checklist 4/24).
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'created_at']);
        });

        Schema::create('sensitive_action_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Somente o hash (SHA-256) do token — padrão Sanctum.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_action_tokens');
        Schema::dropIfExists('verification_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->change();
            $table->dropColumn([
                'uuid',
                'codigo_publico',
                'transaction_password',
                'transaction_password_set_at',
                'status',
            ]);
        });
    }
};
