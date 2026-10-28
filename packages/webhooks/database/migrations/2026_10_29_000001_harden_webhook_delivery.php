<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// Webhooks de saída — endurecimento da entrega (twstec/kit-webhooks).
//
// webhook_endpoints:
// - `failing_since`: desde quando o endpoint falha SEM PARAR (primeira falha
//   depois de um sucesso, da criação ou da reativação). A desativação
//   automática exige as falhas seguidas E este prazo
//   (`webhooks.delivery.disable_after_hours`) — uma queda curta não desativa.
// - `url_changed_at`: a última troca da URL. O reenvio manual recusa entregas
//   criadas antes dela (eventos antigos não vão para um destino novo).
//
// Índices (o PostgreSQL não indexa chave estrangeira sozinho):
// - webhook_deliveries(webhook_event_id): a limpeza e o CASCADE do evento;
// - webhook_events(created_at): a limpeza, que roda para todas as contas;
// - parcial nas entregas EM ABERTO (pending, retrying, delivering), por
//   next_attempt_at: o que o `webhooks:dispatch-pending` varre a cada minuto
//   — pequeno, porque as entregas resolvidas (a imensa maioria) ficam fora.
//   Índice parcial existe no PostgreSQL e no SQLite; em outro banco, o índice
//   (status, next_attempt_at) da migration original atende.
// =============================================================================
return new class extends Migration
{
    private const OPEN_INDEX = 'webhook_deliveries_open_due_index';

    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->timestampTz('failing_since')->nullable();
            $table->timestampTz('url_changed_at')->nullable();
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index('webhook_event_id');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->index('created_at');
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE INDEX '.self::OPEN_INDEX.' ON webhook_deliveries (next_attempt_at) WHERE status IN (\'pending\', \'retrying\', \'delivering\')');
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::OPEN_INDEX);
        }

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['webhook_event_id']);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn(['failing_since', 'url_changed_at']);
        });
    }
};
