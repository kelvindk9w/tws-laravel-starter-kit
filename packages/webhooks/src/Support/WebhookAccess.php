<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Foundation\Audit\AuditTrail;
use Twstec\Kit\Foundation\Identifiers\UuidColumn;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;

/**
 * QUEM PODE o quê nos webhooks da conta atual — e a recusa na trilha.
 *
 * - Ver (lista de endpoints e log de entregas): qualquer membro da conta.
 * - Gerir (criar, alterar, excluir, ativar/desativar, revelar e rotacionar o
 *   segredo, enviar teste, reenviar): dono ou admin — o mesmo papel que gere
 *   as chaves de API. Um webhook manda dado da conta para fora: é uma
 *   integração, como a chave.
 *
 * Recusa: a linha `denied` (a ação tentada, quem, a conta, o alvo) e o MESMO
 * 403/404 de sempre. Endpoint ou entrega de outra conta é 404 idêntico ao
 * inexistente (anti-enumeração), também com a tentativa na trilha.
 */
final class WebhookAccess
{
    public function __construct(private readonly WebhookAudit $audit) {}

    public static function canView(?AuthUser $user = null): bool
    {
        return Accounts::roleOf($user) !== null;
    }

    public static function canManage(?AuthUser $user = null): bool
    {
        return in_array(Accounts::roleOf($user), [AccountRole::Owner, AccountRole::Admin], true);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeManage(AuthUser $actor, WebhookAuditEvent $attempt, ?string $subjectUuid = null, ?string $subjectType = null): void
    {
        if (self::canManage($actor)) {
            return;
        }

        $reason = __('accounts.authorization.denied');

        $this->audit->denied($attempt, Accounts::current(), $actor, $reason, $subjectType ?? WebhookAudit::endpointType(), UuidColumn::isValid($subjectUuid) ? $subjectUuid : null);

        throw new AuthorizationException($reason);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeView(AuthUser $actor): void
    {
        if (! self::canView($actor)) {
            throw new AuthorizationException(__('accounts.authorization.denied'));
        }
    }

    /**
     * Endpoint da CONTA ATUAL pelo uuid.
     *
     * @throws ModelNotFoundException<WebhookEndpoint>
     */
    public function endpoint(AuthUser $actor, string $uuid, WebhookAuditEvent $attempt): WebhookEndpoint
    {
        try {
            return WebhookEndpoint::query()->byUuid($uuid)->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            $this->audit->denied($attempt, Accounts::current(), $actor, __('accounts.authorization.not_found'), WebhookAudit::endpointType(), UuidColumn::isValid($uuid) ? $uuid : null);

            throw $exception;
        }
    }

    /**
     * Entrega da CONTA ATUAL pelo uuid.
     *
     * @throws ModelNotFoundException<WebhookDelivery>
     */
    public function delivery(AuthUser $actor, string $uuid, WebhookAuditEvent $attempt): WebhookDelivery
    {
        try {
            return WebhookDelivery::query()->byUuid($uuid)->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            $this->audit->denied($attempt, Accounts::current(), $actor, __('accounts.authorization.not_found'), AuditTrail::subjectType(WebhookDelivery::class), UuidColumn::isValid($uuid) ? $uuid : null);

            throw $exception;
        }
    }
}
