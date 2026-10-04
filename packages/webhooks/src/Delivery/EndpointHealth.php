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
 * A SAÚDE de um endpoint: tentativas falhas SEGUIDAS.
 *
 * - Sucesso zera a conta.
 * - Falha soma um (UPDATE atômico). Ao chegar em
 *   `webhooks.delivery.disable_after_failures`, o endpoint é DESATIVADO
 *   (`disabled_reason = failures`) — uma vez só, mesmo com várias tentativas
 *   falhando ao mesmo tempo (UPDATE condicionado a estar ativo) —, as
 *   entregas em aberto dele são encerradas como falhas, a desativação vai
 *   para a trilha de auditoria (contexto console, sem pessoa) e para o log, e
 *   o dono e os administradores da conta recebem o AVISO por e-mail.
 *
 * Reativar é ação da tela (Actions\EnableEndpoint), que zera a conta.
 */
final class EndpointHealth
{
    public function __construct(private readonly AuditTrail $trail) {}

    public function succeeded(WebhookEndpoint $endpoint): void
    {
        WebhookEndpoint::query()->whereKey($endpoint->getKey())->update([
            'consecutive_failures' => 0,
            'last_success_at' => Carbon::now(),
        ]);
    }

    public function failed(WebhookEndpoint $endpoint): void
    {
        WebhookEndpoint::query()->whereKey($endpoint->getKey())->increment('consecutive_failures', 1, [
            'last_failure_at' => Carbon::now(),
        ]);

        $limit = (int) config('webhooks.delivery.disable_after_failures', 20);
        $endpoint->refresh();

        if ($limit < 1 || $endpoint->consecutive_failures < $limit || ! $endpoint->isActive()) {
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

            self::closeOpenDeliveries($endpoint);

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
     * Encerra as entregas em aberto do endpoint (desativado ou em
     * desativação): saem como falhas, com o motivo.
     */
    public static function closeOpenDeliveries(WebhookEndpoint $endpoint): void
    {
        WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->getKey())
            ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value])
            ->update([
                'status' => DeliveryStatus::Failed->value,
                'next_attempt_at' => null,
                'error' => __('webhooks.errors.endpoint_disabled'),
            ]);
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
