<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `queued_at`: quando o evento entrou na fila (#120).
 *
 * Nulo = recebido, gravado e AINDA FORA da fila (a fila estava indisponível
 * na hora). O `webhooks:requeue-inbound` agendado põe esses de volta; o aviso
 * nunca se perde nem vira 503 para o provedor (que interrompe a fila do
 * endereço depois de falhas seguidas). Os registros que já existiam entraram
 * na fila quando chegaram: recebem o próprio `received_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_inbound_events', function (Blueprint $table) {
            $table->timestampTz('queued_at')->nullable()->after('received_at');

            $table->index(['status', 'queued_at']);
        });

        DB::table('webhook_inbound_events')->whereNull('queued_at')->update(['queued_at' => DB::raw('received_at')]);
    }

    public function down(): void
    {
        Schema::table('webhook_inbound_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'queued_at']);
            $table->dropColumn('queued_at');
        });
    }
};
