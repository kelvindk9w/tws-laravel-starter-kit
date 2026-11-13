<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// RETENÇÃO (`webhooks:prune`): todo evento é gravado — com ou sem endpoint —,
// então o expurgo é configurável: os sem entrega têm prazo próprio
// (`webhooks.prune.days_without_deliveries`), os com entrega o de sempre
// (`webhooks.prune.days`), e entrega em aberto segura o evento.
// =============================================================================

beforeEach(function (): void {
    Queue::fake();
});

it('sem entrega pelo prazo próprio; com entrega pelo de sempre; entrega em aberto segura o evento', function (): void {
    config(['webhooks.prune.days' => 30, 'webhooks.prune.days_without_deliveries' => 7, 'webhooks.prune.batch' => 2]);
    $owner = $this->owner();
    $account = $this->accountOf($owner);

    // Três eventos sem endpoint, há 10 dias (mais que 7, menos que 30).
    $this->travel(-10)->days();
    foreach ([1, 2, 3] as $n) {
        Webhooks::dispatch($account, 'order.created', ['n' => $n]);
    }
    $this->travelBack();

    // Um sem endpoint recente (fica).
    $recente = Webhooks::dispatch($account, 'order.created', ['n' => 4]);

    // Com endpoint: um resolvido há 40 dias (sai), um resolvido há 10 (fica)
    // e um em aberto há 40 (fica).
    $this->createEndpoint($owner);
    $this->travel(-40)->days();
    $antigo = Webhooks::dispatch($account, 'order.created', ['n' => 5]);
    $aberto = Webhooks::dispatch($account, 'order.created', ['n' => 6]);
    $this->travelBack();
    $this->travel(-10)->days();
    $resolvido = Webhooks::dispatch($account, 'order.created', ['n' => 7]);
    $this->travelBack();

    Accounts::asSystem('teste', function () use ($antigo, $resolvido): void {
        WebhookDelivery::query()->whereIn('webhook_event_id', [$antigo->id, $resolvido->id])->update(['status' => DeliveryStatus::Succeeded->value]);
    });

    $this->artisan('webhooks:prune')->assertSuccessful();

    $restam = DB::table('webhook_events')->orderBy('id')->pluck('uuid')->all();

    expect($restam)->toBe([$recente->uuid, $aberto->uuid, $resolvido->uuid])
        ->and(DB::table('webhook_deliveries')->count())->toBe(2);
});

it('sem prazo próprio, os sem entrega seguem o prazo de sempre', function (): void {
    config(['webhooks.prune.days' => 30, 'webhooks.prune.days_without_deliveries' => null]);
    $owner = $this->owner();
    $account = $this->accountOf($owner);

    $this->travel(-10)->days();
    $dezDias = Webhooks::dispatch($account, 'order.created', ['n' => 1]);
    $this->travelBack();
    $this->travel(-31)->days();
    Webhooks::dispatch($account, 'order.created', ['n' => 2]);
    $this->travelBack();

    $this->artisan('webhooks:prune')->assertSuccessful();

    expect(DB::table('webhook_events')->pluck('uuid')->all())->toBe([$dezDias->uuid]);

    // A opção do comando vale sobre a configuração.
    $this->artisan('webhooks:prune', ['--days-without-deliveries' => 5])->assertSuccessful();

    expect(DB::table('webhook_events')->count())->toBe(0);
});
