<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Webhooks\Actions\Concerns\GuardsWebhookAction;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Signing\SecretGenerator;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * ROTACIONA o segredo — ação sensível, na trilha.
 *
 * Um segredo novo é gerado e devolvido em claro (a tela mostra UMA vez). Com
 * convivência (`$overlapMinutes` > 0, até `webhooks.secret.max_overlap_minutes`),
 * o segredo anterior continua assinando JUNTO com o novo até o prazo — cada
 * envio leva dois `v1=` no cabeçalho — e o receptor troca o dele sem perder
 * evento. Sem convivência (0), o anterior morre na hora. Uma rotação durante
 * outra descarta o anterior do anterior (nunca três).
 */
final class RotateSecret
{
    use GuardsWebhookAction;

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException<WebhookEndpoint>
     * @throws ValidationException
     */
    public function handle(AuthUser $actor, string $endpointUuid, mixed $overlapMinutes, #[\SensitiveParameter] ?string $sensitiveToken): string
    {
        $this->access()->authorizeManage($actor, WebhookAuditEvent::SecretRotated, $endpointUuid);
        $endpoint = $this->access()->endpoint($actor, $endpointUuid, WebhookAuditEvent::SecretRotated);
        $overlap = $this->validatedOverlap($overlapMinutes);

        $this->consumeSensitiveToken($actor, $sensitiveToken, WebhookAuditEvent::SecretRotated, $endpoint->uuid);

        $secret = SecretGenerator::generate();

        DB::transaction(function () use ($actor, $endpoint, $overlap, $secret): void {
            $now = Carbon::now();

            $endpoint->forceFill([
                'previous_secret' => $overlap > 0 ? $endpoint->secret : null,
                'previous_secret_expires_at' => $overlap > 0 ? $now->copy()->addMinutes($overlap) : null,
                'secret' => $secret,
                'secret_rotated_at' => $now,
            ])->save();

            $this->audit()->record(WebhookAuditEvent::SecretRotated, Accounts::current(), $actor, WebhookAudit::endpointType(), $endpoint->uuid, [
                'overlap_minutes' => ['before' => null, 'after' => $overlap],
            ]);
        });

        return $secret;
    }
}
