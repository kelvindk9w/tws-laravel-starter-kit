<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Support;

use Illuminate\Support\Carbon;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;

/**
 * O que as TELAS mostram dos webhooks da conta atual, já em arrays — a mesma
 * forma para o Livewire e para as props do Inertia. Escolhido campo a campo:
 * o segredo NUNCA está aqui (nem cifrado), nem o corpo de um evento.
 */
final class WebhookPanel
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function endpoints(): array
    {
        return WebhookEndpoint::query()
            ->with('project:id,uuid,name')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (WebhookEndpoint $endpoint): array => self::endpoint($endpoint))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function endpoint(WebhookEndpoint $endpoint): array
    {
        $previousValid = $endpoint->previous_secret_expires_at !== null && $endpoint->previous_secret_expires_at->isFuture();

        return [
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'host' => $endpoint->host(),
            'events' => array_values($endpoint->events),
            'event_labels' => array_map(EventCatalog::label(...), array_values($endpoint->events)),
            'project' => $endpoint->project !== null ? ['uuid' => $endpoint->project->uuid, 'name' => $endpoint->project->name] : null,
            'active' => $endpoint->isActive(),
            'status' => $endpoint->status->value,
            'status_label' => $endpoint->status->label(),
            'disabled_reason' => $endpoint->disabled_reason?->value,
            'disabled_reason_label' => $endpoint->disabled_reason?->label(),
            'consecutive_failures' => $endpoint->consecutive_failures,
            'secret_rotated_at' => self::date($endpoint->secret_rotated_at),
            'previous_secret_expires_at' => $previousValid ? self::date($endpoint->previous_secret_expires_at) : null,
            'last_success_at' => self::date($endpoint->last_success_at),
            'last_failure_at' => self::date($endpoint->last_failure_at),
            'created_at' => self::date($endpoint->created_at),
        ];
    }

    /**
     * As entregas mais recentes (de um endpoint, ou da conta toda), com as
     * tentativas.
     *
     * @return list<array<string, mixed>>
     */
    public static function deliveries(?string $endpointUuid = null, int $limit = 50): array
    {
        $query = WebhookDelivery::query()
            ->with(['endpoint:id,uuid,name,account_id', 'event:id,uuid,type,account_id', 'attemptLog' => fn ($attempts) => $attempts->orderBy('attempt')])
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)));

        if ($endpointUuid !== null) {
            $query->whereHas('endpoint', fn ($endpoint) => $endpoint->byUuid($endpointUuid));
        }

        return $query->get()->map(static fn (WebhookDelivery $delivery): array => [
            'uuid' => $delivery->uuid,
            'endpoint' => ['uuid' => $delivery->endpoint->uuid, 'name' => $delivery->endpoint->name],
            'event' => ['uuid' => $delivery->event->uuid, 'type' => $delivery->event->type],
            'status' => $delivery->status->value,
            'status_label' => $delivery->status->label(),
            'attempts' => $delivery->attempts,
            'next_attempt_at' => self::date($delivery->next_attempt_at),
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'error' => $delivery->error,
            'created_at' => self::date($delivery->created_at),
            'attempt_log' => $delivery->attemptLog->map(static fn (WebhookDeliveryAttempt $attempt): array => [
                'attempt' => $attempt->attempt,
                'manual' => $attempt->manual,
                'outcome' => $attempt->outcome->value,
                'outcome_label' => $attempt->outcome->label(),
                'response_status' => $attempt->response_status,
                'duration_ms' => $attempt->duration_ms,
                'response_excerpt' => $attempt->response_excerpt,
                'error' => $attempt->error,
                'created_at' => self::date($attempt->created_at),
            ])->all(),
        ])->all();
    }

    /**
     * Os eventos que um endpoint pode assinar (com "todos" na frente).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function eventOptions(): array
    {
        return array_map(
            static fn (string $event): array => ['value' => $event, 'label' => EventCatalog::label($event)],
            [WebhookEndpoint::ALL_EVENTS, ...EventCatalog::events()],
        );
    }

    /**
     * Os projetos da conta atual (para restringir um endpoint).
     *
     * @return list<array{uuid: string, name: string}>
     */
    public static function projectOptions(): array
    {
        return Project::query()->orderBy('name')->get(['uuid', 'name'])
            ->map(static fn (Project $project): array => ['uuid' => (string) $project->uuid, 'name' => (string) $project->name])
            ->all();
    }

    private static function date(?Carbon $date): ?string
    {
        return $date?->toIso8601String();
    }
}
