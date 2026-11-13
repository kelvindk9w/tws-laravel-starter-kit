<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Twstec\Kit\Accounts\Account\Concerns\BelongsToAccount;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Identifiers\RoutesByUuid;

/**
 * Um EVENTO disparado pelo aplicativo — o OUTBOX.
 *
 * Gravado no banco (na transação de quem disparou, quando há uma) ANTES de ir
 * para a fila: se a fila estiver fora, nada se perde (`webhooks:dispatch-pending`
 * põe de novo). O `uuid` é o identificador do evento que o receptor recebe no
 * corpo e no cabeçalho `X-Webhook-Id`, igual em toda tentativa e em todo
 * reenvio — é por ele que o receptor deduplica.
 *
 * O corpo (`payload`) é CIFRADO no banco (cast `encrypted:array`). Para o
 * envio, payloadForDelivery() devolve o corpo com os objetos preservados —
 * `{}` continua `{}`, nunca vira `[]`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property int|null $project_id
 * @property int|null $created_by
 * @property string $type
 * @property array<array-key, mixed> $payload
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 */
class WebhookEvent extends Model
{
    use BelongsToAccount, HasUuids, RoutesByUuid;

    protected $table = 'webhook_events';

    /**
     * @var list<string>
     */
    protected $fillable = ['account_id', 'project_id', 'created_by', 'type', 'payload', 'occurred_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['payload'];

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
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * O corpo como o receptor deve recebê-lo: o JSON gravado, lido com os
     * objetos preservados (um objeto vazio continua `{}`; o atributo
     * `payload`, por ser array PHP, não distingue `{}` de `[]`). O corpo
     * inteiro vazio sai como objeto — `data` é sempre objeto ou lista.
     */
    public function payloadForDelivery(): mixed
    {
        $raw = $this->getAttributes()['payload'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return new \stdClass;
        }

        $decoded = json_decode($this->fromEncryptedString($raw), false, 512, JSON_THROW_ON_ERROR);

        return $decoded === [] ? new \stdClass : $decoded;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
