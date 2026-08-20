<?php

declare(strict_types=1);

namespace App\Livewire\ApiKeys;

use App\Core\ApiKeys\Http\Requests\StoreApiKeyRequest;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\SensitiveActionService;
use App\Core\Tenancy\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Chaves de API — a tela mais importante do painel (ADR-006, Fase 6).
 *
 * Tudo na MESMA tela (ADR-005): listar, criar (scopes + vínculo N:N com
 * projetos), visualização ÚNICA da secreta, rotacionar (com grace period),
 * revogar e editar vínculos de projetos.
 *
 * ZERO lógica duplicada: criação/rotação/revogação/vínculos delegam ao
 * ApiKeyService (Fase 4) e a confirmação sensível ao SensitiveActionService
 * (Fase 3: senha de transação → código por e-mail → token de uso único,
 * consumido aqui via validateToken — mesmo contrato do middleware da API).
 */
final class Index extends Component
{
    // --- Formulário de criação (inline, mesma tela) --------------------------
    public bool $showCreateForm = false;

    public string $name = '';

    public ?string $expiresAt = null;

    /** Toggle "todas as permissões" (padrão — ADR-006). */
    public bool $allScopes = true;

    /** @var list<string> Seleção granular "recurso:acao" (quando allScopes off). */
    public array $selectedScopes = [];

    /** @var list<string> UUIDs de projetos vinculados (vazio = conta toda). */
    public array $selectedProjectUuids = [];

    // --- Visualização ÚNICA da secreta (ADR-006) ------------------------------
    public ?string $revealedPublicKey = null;

    public ?string $revealedSecretKey = null;

    // --- Rotação ---------------------------------------------------------------
    public ?string $rotatingKeyUuid = null;

    public int $gracePeriodMinutes = 0;

    // --- Revogação --------------------------------------------------------------
    public ?string $revokingKeyUuid = null;

    // --- Vínculo N:N chave ↔ projetos -------------------------------------------
    public ?string $editingProjectsKeyUuid = null;

    /** @var list<string> */
    public array $editingProjectsSelection = [];

    // --- Fluxo de ação sensível (modal) ------------------------------------------
    /** Ação aguardando confirmação: 'create' | 'rotate'. */
    public ?string $pendingAction = null;

    public string $transactionPassword = '';

    public string $verificationCode = '';

    public bool $codeSent = false;

    /**
     * Chaves do usuário (todas — a listagem mostra também o histórico:
     * revogadas, expiradas e rotacionadas com seu status).
     *
     * @return Collection<int, ApiKey>
     */
    public function keys(): Collection
    {
        return ApiKey::query()
            ->where('user_id', $this->user()->id)
            ->with('projects:projects.id,projects.uuid,projects.name')
            ->latest()
            ->get();
    }

    /**
     * Projetos do usuário (para os checkboxes de vínculo).
     *
     * @return Collection<int, Project>
     */
    public function projects(): Collection
    {
        return Project::query()
            ->where('user_id', $this->user()->id)
            ->orderBy('name')
            ->get();
    }

    // =========================================================================
    // Criação
    // =========================================================================

    public function startCreate(): void
    {
        $this->resetValidation();
        $this->reset('name', 'expiresAt', 'selectedScopes', 'selectedProjectUuids');
        $this->allScopes = true;
        $this->showCreateForm = true;
    }

    /**
     * Passo 1 da criação: valida o formulário e abre a confirmação sensível
     * (criação de chave é ação sensível — ADR-006).
     */
    public function requestCreate(): void
    {
        $this->validateKeyForm();

        if (! $this->user()->hasTransactionPassword()) {
            throw ValidationException::withMessages([
                'name' => __('panel.api_keys.sensitive_requires_password'),
            ]);
        }

        $this->showCreateForm = false;
        $this->openSensitiveModal('create');
    }

    // =========================================================================
    // Rotação (ADR-006: herda nome/scopes/projetos; morte da antiga escolhida
    // pelo usuário — imediata ou grace period).
    // =========================================================================

    public function startRotate(string $uuid): void
    {
        $key = $this->findOwnedKey($uuid);

        if (! $key->isUsable()) {
            throw ValidationException::withMessages([
                'rotatingKeyUuid' => __('api_keys.keys.not_rotatable'),
            ]);
        }

        $this->rotatingKeyUuid = $uuid;
        $this->gracePeriodMinutes = 0;
    }

    public function cancelRotate(): void
    {
        $this->reset('rotatingKeyUuid', 'gracePeriodMinutes');
    }

    public function requestRotate(): void
    {
        $this->validate([
            'gracePeriodMinutes' => ['required', 'integer', 'min:0', 'max:'.(int) config('api_keys.rotation.max_grace_minutes', 10080)],
        ]);

        $this->openSensitiveModal('rotate');
    }

    // =========================================================================
    // Revogação (irreversível — confirmação explícita, mesma tela)
    // =========================================================================

    public function startRevoke(string $uuid): void
    {
        $this->findOwnedKey($uuid);
        $this->revokingKeyUuid = $uuid;
    }

    public function cancelRevoke(): void
    {
        $this->reset('revokingKeyUuid');
    }

    public function revoke(ApiKeyService $apiKeys): void
    {
        $key = $this->findOwnedKey((string) $this->revokingKeyUuid);

        $apiKeys->revoke($key);

        $this->reset('revokingKeyUuid');
        session()->flash('keys_status', __('panel.api_keys.revoked'));
    }

    // =========================================================================
    // Vínculo N:N chave ↔ projetos (lista vazia = conta toda — ADR-005/006)
    // =========================================================================

    public function startEditProjects(string $uuid): void
    {
        $key = $this->findOwnedKey($uuid);

        $this->editingProjectsKeyUuid = $uuid;
        $this->editingProjectsSelection = $key->projects->pluck('uuid')->all();
    }

    public function cancelEditProjects(): void
    {
        $this->reset('editingProjectsKeyUuid', 'editingProjectsSelection');
    }

    public function saveProjects(ApiKeyService $apiKeys): void
    {
        $key = $this->findOwnedKey((string) $this->editingProjectsKeyUuid);

        $this->validate([
            'editingProjectsSelection' => ['array'],
            'editingProjectsSelection.*' => ['uuid'],
        ]);

        // resolveProjectIds garante que os projetos pertencem ao dono (Fase 4);
        // uuid de outro tenant vira erro de validação (nunca 500 nem vínculo).
        try {
            $projectIds = $apiKeys->resolveProjectIds($this->user(), $this->editingProjectsSelection);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'editingProjectsSelection' => __('api_keys.projects.invalid'),
            ]);
        }

        $key->projects()->sync($projectIds);

        $this->cancelEditProjects();
        session()->flash('keys_status', __('panel.api_keys.projects_saved'));
    }

    // =========================================================================
    // Fluxo de ação sensível: senha de transação → código por e-mail → executa
    // (consome o SensitiveActionService da Fase 3 — nada duplicado).
    // =========================================================================

    public function sendSensitiveCode(SensitiveActionService $sensitive): void
    {
        $this->validate(['transactionPassword' => ['required', 'string']]);

        try {
            $sensitive->sendCode($this->user(), $this->transactionPassword);
        } catch (ValidationException $exception) {
            throw $this->mapSensitiveErrors($exception);
        }

        $this->codeSent = true;
        $this->reset('verificationCode');
    }

    public function confirmSensitiveAction(SensitiveActionService $sensitive, ApiKeyService $apiKeys): void
    {
        $this->validate(['verificationCode' => ['required', 'string', 'size:6']]);

        try {
            // Código válido → token de ação sensível (uso único, curta duração).
            $issued = $sensitive->confirmCode($this->user(), $this->verificationCode);

            // Consome o token exatamente como o middleware `sensitive.token`
            // faria na API — a operação abaixo é a única autorizada por ele.
            abort_unless($sensitive->validateToken($this->user(), $issued['token']), 403);
        } catch (ValidationException $exception) {
            throw $this->mapSensitiveErrors($exception);
        }

        match ($this->pendingAction) {
            'create' => $this->performCreate($apiKeys),
            'rotate' => $this->performRotate($apiKeys),
            default => null,
        };

        $this->closeSensitiveModal();
    }

    public function cancelSensitiveAction(): void
    {
        $this->closeSensitiveModal();
    }

    /**
     * Segundos restantes de cooldown de reenvio do código (UI desabilita o
     * botão de reenvio durante a janela — mesma regra do service).
     */
    public function resendCooldown(): int
    {
        // Sem DI de método: a view chama $this->resendCooldown() diretamente.
        return app(SensitiveActionService::class)->resendCooldownRemaining($this->user());
    }

    // =========================================================================
    // Tela de visualização única: o usuário confirma que guardou a secreta —
    // as propriedades são limpas e o valor NUNCA mais é exibido (ADR-006).
    // =========================================================================

    public function dismissSecret(): void
    {
        $this->reset('revealedPublicKey', 'revealedSecretKey');
    }

    public function render(): View
    {
        return view('livewire.api-keys.index', [
            'keys' => $this->keys(),
            'projects' => $this->projects(),
            'scopesCatalog' => (array) config('api_keys.scopes_catalog', []),
            'hasTransactionPassword' => $this->user()->hasTransactionPassword(),
        ])->title(__('panel.api_keys.title'));
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * Validação do formulário de chave — MESMAS regras do StoreApiKeyRequest
     * da API v1 (Fase 4), para UI e API se comportarem igual.
     */
    private function validateKeyForm(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
            'selectedScopes' => ['array'],
            'selectedScopes.*' => ['string', 'regex:'.StoreApiKeyRequest::SCOPE_REGEX],
            'selectedProjectUuids' => ['array'],
            'selectedProjectUuids.*' => ['uuid'],
        ], [
            'selectedScopes.*.regex' => __('api_keys.scopes.invalid_format'),
        ], [
            'name' => __('panel.common.name'),
            'expiresAt' => __('panel.api_keys.expires_at'),
        ]);

        if (! $this->allScopes && $this->selectedScopes === []) {
            throw ValidationException::withMessages([
                'selectedScopes' => __('api_keys.scopes.invalid_format'),
            ]);
        }
    }

    private function performCreate(ApiKeyService $apiKeys): void
    {
        $result = $apiKeys->create($this->user(), [
            'name' => $this->name,
            'scopes' => $this->allScopes ? null : array_values($this->selectedScopes),
            'expires_at' => $this->expiresAt !== null && $this->expiresAt !== ''
                ? Carbon::parse($this->expiresAt)->toDateTimeString()
                : null,
            'project_uuids' => $this->selectedProjectUuids !== [] ? array_values($this->selectedProjectUuids) : null,
        ]);

        // A secreta em claro fica SÓ nestas propriedades transitórias, até o
        // usuário confirmar que guardou (dismissSecret). Nunca toca o banco.
        $this->revealedPublicKey = $result['api_key']->public_key;
        $this->revealedSecretKey = $result['secret_key'];

        $this->reset('name', 'expiresAt', 'selectedScopes', 'selectedProjectUuids');
        $this->allScopes = true;
        $this->showCreateForm = false;
    }

    private function performRotate(ApiKeyService $apiKeys): void
    {
        $key = $this->findOwnedKey((string) $this->rotatingKeyUuid);

        $result = $apiKeys->rotate($key, $this->gracePeriodMinutes);

        $this->revealedPublicKey = $result['api_key']->public_key;
        $this->revealedSecretKey = $result['secret_key'];

        $this->reset('rotatingKeyUuid', 'gracePeriodMinutes');
    }

    /**
     * Busca chave do PRÓPRIO usuário por UUID — uuid de outro tenant = 404
     * (checklist itens 11/31, mesmo padrão dos controllers da API).
     */
    private function findOwnedKey(string $uuid): ApiKey
    {
        /** @var ApiKey */
        return ApiKey::query()
            ->where('user_id', $this->user()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function openSensitiveModal(string $action): void
    {
        $this->resetValidation();
        $this->pendingAction = $action;
        $this->codeSent = false;
        $this->reset('transactionPassword', 'verificationCode');
    }

    private function closeSensitiveModal(): void
    {
        $this->pendingAction = null;
        $this->codeSent = false;
        $this->reset('transactionPassword', 'verificationCode');
    }

    /**
     * O SensitiveActionService lança erros com chaves snake_case
     * ('transaction_password', 'code') — mapeia para os campos do modal.
     */
    private function mapSensitiveErrors(ValidationException $exception): ValidationException
    {
        $map = ['transaction_password' => 'transactionPassword', 'code' => 'verificationCode'];

        $errors = [];

        foreach ($exception->errors() as $key => $messages) {
            $errors[$map[$key] ?? $key] = $messages;
        }

        return ValidationException::withMessages($errors);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
