<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Twstec\Kit\Accounts\Account\Concerns\BelongsToAccount;
use Twstec\Kit\Foundation\Identifiers\RoutesByUuid;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;

/**
 * UMA tentativa de entrega (automática ou reenvio manual): o resultado, a
 * duração, o trecho da resposta (CORTADO em
 * `webhooks.delivery.response_excerpt_bytes` e REDIGIDO), o erro sem URL e o
 * `correlation_id` que a liga à linha de `outbound_http_logs`.
 *
 * `created_by`: quem pediu o reenvio manual (nulo nas automáticas).
 *
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property int|null $created_by
 * @property int $webhook_delivery_id
 * @property int $attempt
 * @property bool $manual
 * @property AttemptOutcome $outcome
 * @property int|null $response_status
 * @property int|null $duration_ms
 * @property string|null $response_excerpt
 * @property string|null $error
 * @property string|null $destination_ip
 * @property string|null $correlation_id
 * @property Carbon|null $created_at
 */
class WebhookDeliveryAttempt extends Model
{
    use BelongsToAccount, HasUuids, RoutesByUuid;

    public const UPDATED_AT = null;

    protected $table = 'webhook_delivery_attempts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'created_by',
        'webhook_delivery_id',
        'attempt',
        'manual',
        'outcome',
        'response_status',
        'duration_ms',
        'response_excerpt',
        'error',
        'destination_ip',
        'correlation_id',
    ];

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
            'attempt' => 'integer',
            'manual' => 'boolean',
            'outcome' => AttemptOutcome::class,
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WebhookDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'webhook_delivery_id');
    }
}
