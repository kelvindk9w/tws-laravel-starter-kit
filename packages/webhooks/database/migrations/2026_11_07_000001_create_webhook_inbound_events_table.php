<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// Webhooks RECEBIDOS (twstec/kit-webhooks) — a deduplicação e a trilha do
// recebimento.
//
// webhook_inbound_events: um evento verificado de uma fonte.
// - UNIQUE (`source`, `event_id`): a repetição (o provedor reenvia, alguém
//   repete a requisição capturada) bate aqui. O recebimento grava com INSERT
//   … ON CONFLICT DO NOTHING (no SQLite, INSERT OR IGNORE): sob corrida, o
//   banco decide quem processa — nunca "consulta e depois insere".
// - `payload`: só com `store_payload` ligado, CIFRADO (APP_KEY); `null` por
//   padrão (o corpo viaja só no job, cifrado). `payload_hash`: SHA-256 do
//   corpo cru, para avisar quando o mesmo id chega com corpo diferente.
// - `last_error`: só a classe da exceção do handler, nunca a mensagem.
//
// Sem `account_id`: o registro é do recebimento, não de uma conta (quem liga
// o evento à conta é o handler do aplicativo).
// =============================================================================
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source', 64);
            $table->string('event_id', 255);
            $table->string('event_type', 150)->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->string('status', 20)->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('payload')->nullable();
            $table->char('payload_hash', 64);
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('locked_until')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['source', 'event_id']);
            $table->index('received_at');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbound_events');
    }
};
