<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('panel.api_keys.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.subtitle') }}</p>
        </div>
        @unless ($showCreateForm)
            <button type="button" wire:click="startCreate" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">
                {{ __('panel.api_keys.new') }}
            </button>
        @endunless
    </div>

    @if (session('keys_status'))
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('keys_status') }}</p>
    @endif

    {{-- =====================================================================
         VISUALIZAÇÃO ÚNICA DA SECRETA (ADR-006): exibida 1x, sem recuperação.
         ==================================================================== --}}
    @if ($revealedSecretKey)
        <section class="rounded-xl border-2 border-amber-400 bg-amber-50 p-5 dark:border-amber-500 dark:bg-amber-950/40">
            <h2 class="font-semibold text-amber-900 dark:text-amber-100">{{ __('panel.api_keys.secret_heading') }}</h2>
            <p class="mt-1 text-sm font-medium text-amber-800 dark:text-amber-200">{{ __('panel.api_keys.secret_warning') }}</p>

            <dl class="mt-4 space-y-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('panel.api_keys.public_key') }}</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <code class="break-all rounded bg-white px-2 py-1 text-sm dark:bg-gray-900" data-testid="revealed-public-key">{{ $revealedPublicKey }}</code>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('panel.api_keys.title') }} — sk_</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <code class="break-all rounded bg-white px-2 py-1 text-sm font-semibold dark:bg-gray-900" data-testid="revealed-secret-key">{{ $revealedSecretKey }}</code>
                        <button type="button"
                                onclick="navigator.clipboard.writeText('{{ $revealedSecretKey }}'); this.textContent = '{{ __('panel.api_keys.copied') }}';"
                                class="rounded-lg bg-(--brand) px-3 py-1.5 text-sm font-medium text-white">{{ __('panel.api_keys.copy') }}</button>
                    </dd>
                </div>
            </dl>

            <button type="button" wire:click="dismissSecret"
                    class="mt-4 rounded-lg border border-amber-500 px-4 py-2 text-sm font-medium text-amber-900 dark:text-amber-100">
                {{ __('panel.api_keys.secret_done') }}
            </button>
        </section>
    @endif

    {{-- =====================================================================
         FORMULÁRIO DE CRIAÇÃO (inline — mesma tela, ADR-005)
         ==================================================================== --}}
    @if ($showCreateForm)
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold">{{ __('panel.api_keys.create_heading') }}</h2>

            <form wire:submit="requestCreate" class="mt-4 space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="keyName" class="text-sm font-medium">{{ __('panel.common.name') }}</label>
                        <input id="keyName" type="text" wire:model="name" maxlength="100"
                               class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="expiresAt" class="text-sm font-medium">{{ __('panel.api_keys.expires_at') }} <span class="font-normal text-gray-400">({{ __('panel.common.optional') }})</span></label>
                        <input id="expiresAt" type="datetime-local" wire:model="expiresAt"
                               class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                        <p class="mt-1 text-xs text-gray-500">{{ __('panel.api_keys.expires_hint') }}</p>
                        @error('expiresAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Scopes: toggle "todas" (padrão) + seleção granular --}}
                <fieldset>
                    <legend class="text-sm font-medium">{{ __('panel.api_keys.scopes_heading') }}</legend>
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="checkbox" wire:model.live="allScopes" class="mt-0.5 rounded">
                        <span>
                            <span class="font-medium">{{ __('panel.api_keys.scopes_all') }}</span>
                            <span class="block text-xs text-gray-500">{{ __('panel.api_keys.scopes_all_hint') }}</span>
                        </span>
                    </label>

                    @unless ($allScopes)
                        <p class="mt-3 text-xs text-gray-500">{{ __('panel.api_keys.scopes_hint') }}</p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($scopesCatalog as $resource => $actions)
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-sm font-medium">{{ __('panel.api_keys.scope_resource_'.$resource) }}</p>
                                    <div class="mt-1.5 space-y-1">
                                        @foreach ($actions as $action)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" wire:model="selectedScopes" value="{{ $resource }}:{{ $action }}" class="rounded">
                                                {{ __('panel.api_keys.scope_action_'.$action) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('selectedScopes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endunless
                </fieldset>

                {{-- Vínculo N:N com projetos (vazio = conta toda) --}}
                <fieldset>
                    <legend class="text-sm font-medium">{{ __('panel.api_keys.projects_heading') }}</legend>
                    <p class="mt-1 text-xs text-gray-500">{{ __('panel.api_keys.projects_hint') }}</p>
                    @if ($projects->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">{{ __('panel.api_keys.projects_empty') }}</p>
                    @else
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($projects as $project)
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm dark:border-gray-700">
                                    <input type="checkbox" wire:model="selectedProjectUuids" value="{{ $project->uuid }}" class="rounded">
                                    {{ $project->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.common.create') }}</button>
                    <button type="button" wire:click="$set('showCreateForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                </div>
            </form>
        </section>
    @endif

    {{-- =====================================================================
         LISTAGEM (cada chave com status, último uso, validade e ações)
         ==================================================================== --}}
    @if ($keys->isEmpty() && ! $showCreateForm)
        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">
            {{ __('panel.api_keys.empty') }}
        </p>
    @endif

    <div class="space-y-3">
        @foreach ($keys as $key)
            @php
                $statusLabel = match ($key->status) {
                    \App\Core\ApiKeys\Enums\ApiKeyStatus::Active => __('panel.api_keys.status_active'),
                    \App\Core\ApiKeys\Enums\ApiKeyStatus::Revoked => __('panel.api_keys.status_revoked'),
                    \App\Core\ApiKeys\Enums\ApiKeyStatus::Expired => __('panel.api_keys.status_expired'),
                    \App\Core\ApiKeys\Enums\ApiKeyStatus::ExpiredInactivity => __('panel.api_keys.status_expired_inactivity'),
                    \App\Core\ApiKeys\Enums\ApiKeyStatus::Rotated => __('panel.api_keys.status_rotated'),
                };
                $inGrace = $key->grace_ends_at !== null && $key->grace_ends_at->isFuture();
            @endphp
            <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium">{{ $key->name }}</h3>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium',
                                          'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' => $key->isUsable(),
                                          'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! $key->isUsable()])>
                                {{ $inGrace ? __('panel.api_keys.status_grace') : $statusLabel }}
                            </span>
                        </div>
                        <p class="mt-1 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $key->public_key }}</p>
                        <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <div><dt class="inline font-medium">{{ __('panel.api_keys.last_used') }}:</dt> <dd class="inline">{{ $key->last_used_at?->setTimezone(platform()->displayTimezone)->format('d/m/Y H:i') ?? __('panel.common.never') }}</dd></div>
                            <div><dt class="inline font-medium">{{ __('panel.api_keys.expires_at') }}:</dt> <dd class="inline">{{ $key->expires_at?->setTimezone(platform()->displayTimezone)->format('d/m/Y H:i') ?? __('panel.api_keys.no_expiration') }}</dd></div>
                            <div>
                                <dt class="inline font-medium">{{ __('panel.api_keys.projects_heading') }}:</dt>
                                <dd class="inline">{{ $key->projects->isEmpty() ? __('panel.api_keys.whole_account') : $key->projects->pluck('name')->implode(', ') }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if ($key->isUsable())
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="startEditProjects('{{ $key->uuid }}')"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium dark:border-gray-700">{{ __('panel.api_keys.edit_projects') }}</button>
                            <button type="button" wire:click="startRotate('{{ $key->uuid }}')"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium dark:border-gray-700">{{ __('panel.api_keys.rotate') }}</button>
                            <button type="button" wire:click="startRevoke('{{ $key->uuid }}')"
                                    class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 dark:border-red-800 dark:text-red-400">{{ __('panel.api_keys.revoke') }}</button>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- =====================================================================
         MODAL: rotação (escolha do grace period — ADR-006)
         ==================================================================== --}}
    @if ($rotatingKeyUuid)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 dark:bg-gray-900" role="dialog" aria-modal="true">
                <h2 class="font-semibold">{{ __('panel.api_keys.rotate_title') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.rotate_warning') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.grace_hint') }}</p>

                <div class="mt-4 space-y-2">
                    @foreach ([0 => 'grace_immediate', 60 => 'grace_1h', 1440 => 'grace_24h', 10080 => 'grace_7d'] as $minutes => $labelKey)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                            <input type="radio" wire:model="gracePeriodMinutes" value="{{ $minutes }}">
                            {{ __('panel.api_keys.'.$labelKey) }}
                        </label>
                    @endforeach
                </div>
                @error('gracePeriodMinutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="requestRotate" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.common.confirm') }}</button>
                    <button type="button" wire:click="cancelRotate" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- =====================================================================
         MODAL: revogação (irreversível)
         ==================================================================== --}}
    @if ($revokingKeyUuid)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 dark:bg-gray-900" role="dialog" aria-modal="true">
                <h2 class="font-semibold">{{ __('panel.api_keys.revoke_title') }}</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('panel.api_keys.revoke_warning', ['name' => $keys->firstWhere('uuid', $revokingKeyUuid)?->name]) }}
                </p>
                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="revoke" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white">{{ __('panel.api_keys.revoke') }}</button>
                    <button type="button" wire:click="cancelRevoke" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- =====================================================================
         MODAL: vínculo N:N chave ↔ projetos
         ==================================================================== --}}
    @if ($editingProjectsKeyUuid)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 dark:bg-gray-900" role="dialog" aria-modal="true">
                <h2 class="font-semibold">{{ __('panel.api_keys.projects_heading') }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.projects_hint') }}</p>

                <div class="mt-4 space-y-2">
                    @forelse ($projects as $project)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                            <input type="checkbox" wire:model="editingProjectsSelection" value="{{ $project->uuid }}" class="rounded">
                            {{ $project->name }}
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('panel.api_keys.projects_empty') }}</p>
                    @endforelse
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="saveProjects" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.common.save') }}</button>
                    <button type="button" wire:click="cancelEditProjects" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- =====================================================================
         MODAL: AÇÃO SENSÍVEL (senha de transação → código por e-mail → confirma)
         ==================================================================== --}}
    @if ($pendingAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 dark:bg-gray-900" role="dialog" aria-modal="true">
                <h2 class="font-semibold">{{ __('panel.api_keys.sensitive_heading') }}</h2>

                @unless ($codeSent)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.sensitive_password_hint') }}</p>
                    <form wire:submit="sendSensitiveCode" class="mt-4 space-y-3">
                        <div>
                            <label for="transactionPassword" class="text-sm font-medium">{{ __('auth.ui.transaction_password_title') }}</label>
                            <input id="transactionPassword" type="password" wire:model="transactionPassword" autocomplete="off"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                            @error('transactionPassword') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.api_keys.sensitive_send_code') }}</button>
                            <button type="button" wire:click="cancelSensitiveAction" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                        </div>
                    </form>
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.api_keys.sensitive_code_hint') }}</p>
                    <form wire:submit="confirmSensitiveAction" class="mt-4 space-y-3">
                        <div>
                            <label for="verificationCode" class="text-sm font-medium">{{ __('panel.api_keys.sensitive_code') }}</label>
                            <input id="verificationCode" type="text" wire:model="verificationCode" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-center text-lg tracking-widest dark:border-gray-700">
                            @error('verificationCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.api_keys.sensitive_confirm') }}</button>
                            <button type="button" wire:click="sendSensitiveCode" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700" @disabled($this->resendCooldown() > 0)>
                                @if ($this->resendCooldown() > 0)
                                    {{ __('panel.api_keys.sensitive_resend_in', ['seconds' => $this->resendCooldown()]) }}
                                @else
                                    {{ __('panel.api_keys.sensitive_send_code') }}
                                @endif
                            </button>
                            <button type="button" wire:click="cancelSensitiveAction" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                        </div>
                    </form>
                @endunless
            </div>
        </div>
    @endif
</div>
