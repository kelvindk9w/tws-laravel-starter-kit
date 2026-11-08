<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Twstec\Kit\Webhooks\Enums\InboundStatus;

/**
 * Um webhook RECEBIDO e verificado — o registro de DEDUPLICAÇÃO e a trilha
 * do recebimento (webhook_inbound_events).
 *
 * Único por (`source`, `event_id`): o INSERT com conflito ignorado é o que
 * decide, sob corrida, quem processa (ver Inbound\InboundReceiver). Não é
 * dado de uma conta (sem `account_id`): quem liga o evento a uma conta é o
 * handler do aplicativo.
 *
 * O corpo NÃO fica aqui por padrão — viaja só no job, cifrado. Com
 * `store_payload`, fica cifrado (`encrypted:array`, APP_KEY), fora da
 * serialização, e é apagado pelo `webhooks:prune-inbound` depois de
 * `webhooks.inbound.retention.payload_days`. `payload_hash` é o SHA-256 do
 * corpo cru (detecta o mesmo id com corpo diferente). `last_error` guarda só
 * a classe da exceção do handler — a mensagem pode carregar dado do evento.
 *
 * @property int $id
 * @property string $uuid
 * @property string $source
 * @property string $event_id
 * @property string|null $event_type
 * @property Carbon|null $occurred_at
 * @property InboundStatus $status
 * @property int $attempts
 * @property array<array-key, mixed>|null $payload
 * @property string $payload_hash
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $locked_until
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InboundWebhookEvent extends Model
{
    use HasUuids;

    protected $table = 'webhook_inbound_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source', 'event_id', 'event_type', 'occurred_at', 'status', 'attempts',
        'payload', 'payload_hash', 'received_at', 'processed_at', 'locked_until', 'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['payload', 'payload_hash'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'status' => InboundStatus::class,
            'attempts' => 'integer',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }
}
