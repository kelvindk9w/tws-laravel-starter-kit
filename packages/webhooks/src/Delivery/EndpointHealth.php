<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Support\UserModel;
use Twstec\Kit\Foundation\Audit\AuditScope;
use Twstec\Kit\Foundation\Audit\AuditTrail;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\DisabledReason;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Mail\EndpointDisabledMail;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * A SAÚDE de um endpoint: tentativas falhas SEGUIDAS e desde quando ele falha
 * sem parar (`failing_since`).
 *
 * - Sucesso zera as duas.
 * - Falha soma um (UPDATE atômico) e marca `failing_since` se ainda não
 *   houver. O endpoint é DESATIVADO (`disabled_reason = failures`) só quando
 *   as DUAS condições valem: `webhooks.delivery.disable_after_failures`
 *   falhas seguidas E falhando há pelo menos
 *   `webhooks.delivery.disable_after_hours` horas (0 = só a contagem). Um
 *   receptor fora do ar por alguns minutos, mesmo com muito volume, não é
 *   desativado: as novas tentativas dão conta.
 * - Na desativação — uma vez só, mesmo com várias tentativas falhando ao
 *   mesmo tempo (UPDATE condicionado a estar ativo) — as entregas em aberto
 *   ficam PAUSADAS (nunca falhas: saem ao reativar), a desativação vai para a
 *   trilha de auditoria (contexto console, sem pessoa) e para o log, e o dono
 *   e os administradores da conta recebem o AVISO por e-mail.
 *
 * Reativar é ação da tela (Actions\SetEndpointStatus), que zera a conta e
 * devolve as pausadas à fila (resumePausedDeliveries).
 */
final class EndpointHealth
{
    public function __construct(private readonly AuditTrail $trail) {}

    public function succeeded(WebhookEndpoint $endpoint): void
    {
        WebhookEndpoint::query()->whereKey($endpoint->getKey())->update([
            'consecutive_failures' => 0,
            'failing_since' => null,
            'last_success_at' => Carbon::now(),
        ]);
    }

    public function failed(WebhookEndpoint $endpoint): void
    {
        $now = Carbon::now();

        WebhookEndpoint::query()->whereKey($endpoint->getKey())->increment('consecutive_failures', 1, [
            'last_failure_at' => $now,
        ]);

        WebhookEndpoint::query()->whereKey($endpoint->getKey())->whereNull('failing_since')->update([
            'failing_since' => $now,
        ]);

        $limit = (int) config('webhooks.delivery.disable_after_failures', 20);
        $hours = max(0, (int) config('webhooks.delivery.disable_after_hours', 72));
        $endpoint->refresh();

        if ($limit < 1 || $endpoint->consecutive_failures < $limit || ! $endpoint->isActive()) {
            return;
        }

        if ($hours > 0 && ($endpoint->failing_since === null || $endpoint->failing_since->greaterThan($now->copy()->subHours($hours)))) {
            return;
        }

        $this->disableForFailures($endpoint);
    }

    private function disableForFailures(WebhookEndpoint $endpoint): void
    {
        $disabled = DB::transaction(function () use ($endpoint): bool {
            $now = Carbon::now();

            $changed = WebhookEndpoint::query()
                ->whereKey($endpoint->getKey())
                ->where('status', EndpointStatus::Active->value)
                ->update([
                    'status' => EndpointStatus::Disabled->value,
                    'disabled_reason' => DisabledReason::Failures->value,
                    'disabled_at' => $now,
                ]);

            if ($changed !== 1) {
                return false;
            }

            self::pauseOpenDeliveries($endpoint);

            $this->trail->within(AuditScope::console('webhooks:deliver', 'disabled'), fn () => $this->trail->record(
                WebhookAuditEvent::EndpointDisabled->value,
                null,
                [
                    'status' => ['before' => EndpointStatus::Active->value, 'after' => EndpointStatus::Disabled->value],
                    'reason' => ['before' => null, 'after' => DisabledReason::Failures->value],
                    'consecutive_failures' => ['before' => null, 'after' => $endpoint->consecutive_failures],
                ],
                WebhookAudit::endpointType(),
                $endpoint->uuid,
                Accounts::current()?->uuid,
            ));

            return true;
        });

        if (! $disabled) {
            return;
        }

        Log::warning('webhooks.endpoint_disabled', [
            'endpoint' => $endpoint->uuid,
            'tenant_uuid' => Accounts::current()?->uuid,
            'host' => $endpoint->host(),
            'consecutive_failures' => $endpoint->consecutive_failures,
        ]);

        $this->notify($endpoint->refresh());
    }

    /**
     * PAUSA as entregas do endpoint (desativado ou em desativação) que ainda
     * não saíram: pendentes e aguardando nova tentativa. A que está "em
     * envio" termina a tentativa dela e, ao gravar, também vira pausada. Nada
     * é dado como falha por causa da desativação.
     */
    public static function pauseOpenDeliveries(WebhookEndpoint $endpoint): void
    {
        WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->getKey())
            ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value])
            ->update([
                'status' => DeliveryStatus::Paused->value,
                'next_attempt_at' => null,
                'queued_at' => null,
                'error' => __('webhooks.errors.paused'),
            ]);
    }

    /**
     * REATIVAÇÃO: as entregas pausadas voltam a sair — as criadas dentro do
     * prazo de retenção (`webhooks.prune.days`) voltam a `pending` e vão para
     * a fila depois do commit; as mais velhas (o evento já sairia na limpeza)
     * viram falhas, com o motivo. Chamado dentro da transação da reativação.
     *
     * @return int quantas voltaram à fila
     */
    public static function resumePausedDeliveries(WebhookEndpoint $endpoint): int
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->subDays(max(1, (int) config('webhooks.prune.days', 30)));

        WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->getKey())
            ->where('status', DeliveryStatus::Paused->value)
            ->where('created_at', '<', $cutoff)
            ->update([
                'status' => DeliveryStatus::Failed->value,
                'next_attempt_at' => null,
                'error' => __('webhooks.errors.paused_expired'),
            ]);

        $resumed = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->getKey())
            ->where('status', DeliveryStatus::Paused->value)
            ->orderBy('id')
            ->get();

        if ($resumed->isEmpty()) {
            return 0;
        }

        WebhookDelivery::query()->whereKey($resumed->modelKeys())->update([
            'status' => DeliveryStatus::Pending->value,
            'next_attempt_at' => $now,
            'locked_until' => null,
            'error' => null,
        ]);

        $queue = app(DeliveryQueue::class);

        foreach ($resumed as $delivery) {
            $queue->push($delivery);
        }

        return $resumed->count();
    }

    /**
     * O aviso ao dono e aos administradores da conta (fila, KitMailable). Uma
     * falha ao enfileirar o e-mail não desfaz a desativação: vai para o log.
     */
    private function notify(WebhookEndpoint $endpoint): void
    {
        try {
            /** @var Account $account */
            $account = $endpoint->account()->firstOrFail();

            $ids = AccountMembership::query()
                ->where('account_id', $account->getKey())
                ->whereIn('role', [AccountRole::Owner->value, AccountRole::Admin->value])
                ->pluck('user_id');

            foreach (UserModel::query()->whereKey($ids)->get() as $person) {
                Mail::to($person)->queue(new EndpointDisabledMail($endpoint->name, $endpoint->host(), $endpoint->consecutive_failures, $account->displayName()));
            }
        } catch (Throwable $exception) {
            Log::error('webhooks.endpoint_disabled_notice_failed', [
                'endpoint' => $endpoint->uuid,
                'exception' => $exception::class,
            ]);
        }
    }
}
