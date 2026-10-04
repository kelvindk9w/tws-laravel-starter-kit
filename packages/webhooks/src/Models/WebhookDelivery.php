<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Twstec\Kit\Accounts\Account\Concerns\BelongsToAccount;
use Twstec\Kit\Foundation\Identifiers\RoutesByUuid;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;

/**
 * Uma ENTREGA: um evento para um endpoint (única por par), com o estado e o
 * resultado da última tentativa. O histórico de cada tentativa fica em
 * WebhookDeliveryAttempt.
 *
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property int|null $created_by
 * @property int $webhook_endpoint_id
 * @property int $webhook_event_id
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $locked_until
 * @property Carbon|null $queued_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $delivered_at
 * @property int|null $response_status
 * @property int|null $duration_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookEndpoint $endpoint
 * @property-read WebhookEvent $event
 */
class WebhookDelivery extends Model
{
    use BelongsToAccount, HasUuids, RoutesByUuid;

    protected $table = 'webhook_deliveries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'created_by',
        'webhook_endpoint_id',
        'webhook_event_id',
        'status',
        'attempts',
        'next_attempt_at',
        'locked_until',
        'queued_at',
        'last_attempt_at',
        'delivered_at',
        'response_status',
        'duration_ms',
        'error',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
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
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'locked_until' => 'datetime',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return BelongsTo<WebhookEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    /**
     * @return HasMany<WebhookDeliveryAttempt, $this>
     */
    public function attemptLog(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class, 'webhook_delivery_id');
    }
}
