<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// Webhooks de saída (twstec/kit-webhooks).
//
// webhook_endpoints: o destino, da CONTA (e opcionalmente de um projeto dela).
// - `secret` e `previous_secret`: o segredo de assinatura CIFRADO (cast
//   `encrypted`, APP_KEY) — o HMAC precisa dele em claro para assinar, então
//   hash não serve. `previous_secret` só existe durante a convivência depois
//   de uma rotação (`previous_secret_expires_at`).
// - `events`: os eventos assinados (lista; `*` = todos).
// - `consecutive_failures`: tentativas falhas seguidas; ao chegar no limite o
//   endpoint é desativado (`disabled_reason = failures`).
//
// webhook_events: o OUTBOX — cada evento disparado pelo aplicativo, gravado
// antes da fila. `payload` é CIFRADO. `uuid` é o identificador que o receptor
// usa para deduplicar.
//
// webhook_deliveries: um evento para um endpoint (único por par), com o estado
// e o último resultado. webhook_delivery_attempts: cada tentativa (automática
// ou reenvio manual), com status, duração, trecho REDIGIDO da resposta e o
// correlation_id que liga a tentativa à trilha `outbound_http_logs`.
//
// Exclusão: tudo pertence à conta (`account_id`, ON DELETE CASCADE) e sai com
// ela; projeto excluído leva os endpoints e eventos dele; `created_by` e
// `triggered_by` ficam nulos quando a pessoa sai (o registro é da conta).
// =============================================================================
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->json('events');
            $table->text('secret');
            $table->text('previous_secret')->nullable();
            $table->timestampTz('previous_secret_expires_at')->nullable();
            $table->timestampTz('secret_rotated_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('disabled_reason', 20)->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->timestampsTz();

            $table->index(['account_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->text('payload');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['account_id', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['webhook_endpoint_id', 'webhook_event_id']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('webhook_delivery_id')->constrained('webhook_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('attempt');
            $table->boolean('manual')->default(false);
            $table->string('outcome', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->string('error', 500)->nullable();
            $table->string('destination_ip', 45)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['webhook_delivery_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
    }
};
