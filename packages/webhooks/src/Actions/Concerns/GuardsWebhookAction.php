<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Actions\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Webhooks\Enums\WebhookAuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\BlockedDestinationException;
use Twstec\Kit\Webhooks\Security\DestinationPolicy;
use Twstec\Kit\Webhooks\Support\EventCatalog;
use Twstec\Kit\Webhooks\Support\WebhookAccess;
use Twstec\Kit\Webhooks\Support\WebhookAudit;

/**
 * O que as Actions de webhook têm em comum: o papel, a ação sensível, a
 * validação do formulário e a política de destino — tudo com a recusa na
 * trilha. Fica AQUI (e não nas telas) para nenhuma tela esquecer: os dois
 * starters chamam as mesmas Actions.
 */
trait GuardsWebhookAction
{
    protected function access(): WebhookAccess
    {
        return app(WebhookAccess::class);
    }

    protected function audit(): WebhookAudit
    {
        return app(WebhookAudit::class);
    }

    /**
     * Consome o token de AÇÃO SENSÍVEL (senha de transação → código por
     * e-mail → token de uso único) — o mesmo contrato do middleware
     * `sensitive.token` da API. Inválido, vencido ou já usado: recusa, com a
     * tentativa na trilha.
     *
     * @throws AuthorizationException
     */
    protected function consumeSensitiveToken(AuthUser $actor, #[\SensitiveParameter] ?string $token, WebhookAuditEvent $attempt, ?string $subjectUuid = null): void
    {
        if ($token !== null && $token !== '' && app(SensitiveActionService::class)->validateToken($actor, $token)) {
            return;
        }

        $reason = __('webhooks.errors.sensitive_required');

        $this->audit()->denied($attempt, Accounts::current(), $actor, $reason, WebhookAudit::endpointType(), $subjectUuid);

        throw new AuthorizationException($reason);
    }

    /**
     * Valida o formulário (nome, URL, eventos, projeto) e confere o destino
     * (SSRF, com o DNS de agora). Recusa de destino vai para a trilha.
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, url: string, events: list<string>, project: Project|null}
     *
     * @throws ValidationException
     */
    protected function validatedEndpoint(AuthUser $actor, array $data, WebhookAuditEvent $attempt, ?string $subjectUuid = null): array
    {
        $catalog = EventCatalog::events();

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:'.DestinationPolicy::MAX_URL_LENGTH],
            'events' => ['required', 'array', 'min:1', 'max:200'],
            'events.*' => ['string', 'distinct', Rule::in([WebhookEndpoint::ALL_EVENTS, ...$catalog])],
            'project' => ['nullable', 'uuid'],
        ], [
            'events.*.in' => __('webhooks.validation.unknown_event'),
            'events.required' => __('webhooks.validation.events_required'),
            'events.min' => __('webhooks.validation.events_required'),
        ], [
            'name' => __('webhooks.fields.name'),
            'url' => __('webhooks.fields.url'),
            'events' => __('webhooks.fields.events'),
            'project' => __('webhooks.fields.project'),
        ])->validate();

        $project = null;

        if (($validated['project'] ?? null) !== null) {
            $project = Project::query()->byUuid((string) $validated['project'])->first();

            if ($project === null) {
                throw ValidationException::withMessages(['project' => __('webhooks.validation.project_invalid')]);
            }
        }

        $events = array_values(array_map('strval', (array) $validated['events']));

        // "Todos" já cobre o resto: guarda só o `*`.
        if (in_array(WebhookEndpoint::ALL_EVENTS, $events, true)) {
            $events = [WebhookEndpoint::ALL_EVENTS];
        }

        $url = trim((string) $validated['url']);

        try {
            app(DestinationPolicy::class)->check($url);
        } catch (BlockedDestinationException $blocked) {
            $this->audit()->denied($attempt, Accounts::current(), $actor, $blocked->reason.': '.$blocked->getMessage(), WebhookAudit::endpointType(), $subjectUuid);

            throw ValidationException::withMessages(['url' => $blocked->getMessage()]);
        }

        return ['name' => trim((string) $validated['name']), 'url' => $url, 'events' => $events, 'project' => $project];
    }

    /**
     * Minutos de convivência dos dois segredos numa rotação.
     *
     * @throws ValidationException
     */
    protected function validatedOverlap(mixed $minutes): int
    {
        $max = max(0, (int) config('webhooks.secret.max_overlap_minutes', 10080));

        Validator::make(['overlap' => $minutes], ['overlap' => ['required', 'integer', 'min:0', 'max:'.$max]], [], ['overlap' => __('webhooks.fields.overlap')])->validate();

        return (int) $minutes;
    }
}
