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
use Twstec\Kit\Webhooks\Enums\DisabledReason;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;

/**
 * Um DESTINO de webhooks da conta (opcionalmente restrito a um projeto dela).
 *
 * Pertence à CONTA (BelongsToAccount: toda consulta sai filtrada pela conta
 * atual). O SEGREDO de assinatura é guardado CIFRADO (cast `encrypted`, com a
 * APP_KEY — `APP_PREVIOUS_KEYS` continua decifrando depois de uma troca): o
 * atributo em memória, num dump ou num log do model é o texto cifrado; o
 * valor em claro só existe na hora de assinar e na tela que o mostra uma vez.
 * Fora da serialização (`$hidden`): nem cifrado ele sai num toArray/JSON.
 *
 * Criado, alterado e excluído SÓ pelas Actions do pacote (Actions\*), que
 * conferem o papel, o destino (SSRF) e a ação sensível e gravam a trilha.
 *
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property int|null $project_id
 * @property int|null $created_by
 * @property string $name
 * @property string $url
 * @property list<string> $events
 * @property string $secret
 * @property string|null $previous_secret
 * @property Carbon|null $previous_secret_expires_at
 * @property Carbon|null $secret_rotated_at
 * @property EndpointStatus $status
 * @property DisabledReason|null $disabled_reason
 * @property Carbon|null $disabled_at
 * @property int $consecutive_failures
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 */
class WebhookEndpoint extends Model
{
    use BelongsToAccount, HasUuids, RoutesByUuid;

    /**
     * Assinatura de todos os eventos do catálogo.
     */
    public const ALL_EVENTS = '*';

    protected $table = 'webhook_endpoints';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'project_id',
        'created_by',
        'name',
        'url',
        'events',
        'secret',
        'previous_secret',
        'previous_secret_expires_at',
        'secret_rotated_at',
        'status',
        'disabled_reason',
        'disabled_at',
        'consecutive_failures',
        'last_success_at',
        'last_failure_at',
    ];

    /**
     * Nem cifrado o segredo sai numa serialização.
     *
     * @var list<string>
     */
    protected $hidden = ['secret', 'previous_secret'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'consecutive_failures' => 0,
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
            'events' => 'array',
            'secret' => 'encrypted',
            'previous_secret' => 'encrypted',
            'previous_secret_expires_at' => 'datetime',
            'secret_rotated_at' => 'datetime',
            'status' => EndpointStatus::class,
            'disabled_reason' => DisabledReason::class,
            'disabled_at' => 'datetime',
            'consecutive_failures' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
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

    public function isActive(): bool
    {
        return $this->status === EndpointStatus::Active;
    }

    /**
     * O endpoint assina este evento? O teste (`webhook.ping`) vai para
     * qualquer endpoint, só pelo botão.
     */
    public function subscribesTo(string $type): bool
    {
        $events = $this->events;

        return in_array(self::ALL_EVENTS, $events, true) || in_array($type, $events, true);
    }

    /**
     * Os segredos que assinam AGORA: o atual e, durante a convivência depois
     * de uma rotação, o anterior.
     *
     * @return list<string>
     */
    public function signingSecrets(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $secrets = [$this->secret];

        if ($this->previous_secret !== null && $this->previous_secret_expires_at !== null && $this->previous_secret_expires_at->greaterThan($now)) {
            $secrets[] = $this->previous_secret;
        }

        return $secrets;
    }

    /**
     * Só o host do destino (o que vai para a trilha e para os logs — nunca a
     * URL inteira, que pode carregar token no caminho ou na query).
     */
    public function host(): string
    {
        return strtolower((string) parse_url($this->url, PHP_URL_HOST));
    }
}
