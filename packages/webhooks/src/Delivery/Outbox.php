<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Models\WebhookEvent;
use Twstec\Kit\Webhooks\Support\EventCatalog;

/**
 * O OUTBOX: grava o evento e uma entrega por endpoint que o assina, no
 * banco, ANTES de qualquer coisa ir para a fila.
 *
 * - Dentro da transação de quem disparou (se houver uma): o evento só existe
 *   se a mudança de negócio for confirmada — e, confirmado, não se perde.
 * - As tentativas vão para a fila DEPOIS do commit (`afterCommit`). Se a fila
 *   estiver fora, a entrega fica pendente no banco e o
 *   `webhooks:dispatch-pending` (agendado) a põe na fila.
 * - Sem endpoint ativo que assine o evento (na conta e, se o evento é de um
 *   projeto, sem projeto ou do mesmo projeto), nada é gravado.
 */
final class Outbox
{
    public function __construct(private readonly DeliveryQueue $queue) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @throws InvalidArgumentException evento fora do catálogo, projeto de outra conta ou corpo que não vira JSON
     */
    public function dispatch(Account $account, string $type, array $payload, ?Project $project = null): ?WebhookEvent
    {
        EventCatalog::assertDispatchable($type);

        return $this->record($account, $type, $payload, $project, null);
    }

    /**
     * Grava o evento e as entregas (para os endpoints dados ou para os que o
     * assinam) e põe as tentativas na fila depois do commit.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  list<WebhookEndpoint>|null  $only  endpoints explícitos (o teste); nulo = os que assinam
     *
     * @internal
     */
    public function record(Account $account, string $type, array $payload, ?Project $project, ?array $only): ?WebhookEvent
    {
        if ($project !== null && (int) $project->account_id !== (int) $account->getKey()) {
            throw new InvalidArgumentException('O projeto do evento de webhook não é da conta informada.');
        }

        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('O corpo do evento de webhook não pode ser convertido em JSON.', 0, $exception);
        }

        return Accounts::actingAs($account, function () use ($type, $payload, $project, $only): ?WebhookEvent {
            [$event, $deliveries] = DB::transaction(function () use ($type, $payload, $project, $only): array {
                $endpoints = $only ?? WebhookEndpoint::query()
                    ->where('status', EndpointStatus::Active->value)
                    ->where(fn ($query) => $project === null
                        ? $query->whereNull('project_id')
                        : $query->whereNull('project_id')->orWhere('project_id', $project->getKey()))
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($type))
                    ->values()
                    ->all();

                if ($endpoints === []) {
                    return [null, []];
                }

                $now = Carbon::now();

                $event = WebhookEvent::query()->create([
                    'project_id' => $project?->getKey(),
                    'type' => $type,
                    'payload' => $payload,
                    'occurred_at' => $now,
                ]);

                $deliveries = [];

                foreach ($endpoints as $endpoint) {
                    $deliveries[] = WebhookDelivery::query()->create([
                        'webhook_endpoint_id' => $endpoint->getKey(),
                        'webhook_event_id' => $event->getKey(),
                        'status' => DeliveryStatus::Pending->value,
                        'next_attempt_at' => $now,
                    ]);
                }

                return [$event, $deliveries];
            });

            foreach ($deliveries as $delivery) {
                $this->queue->push($delivery);
            }

            return $event;
        });
    }
}
