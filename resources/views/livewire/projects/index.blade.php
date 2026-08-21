<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('panel.projects.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.projects.subtitle') }}</p>
        </div>
        @unless ($showCreateForm)
            <button type="button" wire:click="startCreate" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">
                {{ __('panel.projects.new') }}
            </button>
        @endunless
    </div>

    @if (session('projects_status'))
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('projects_status') }}</p>
    @endif

    {{-- Criação inline (mesma tela — ADR-005) --}}
    @if ($showCreateForm)
        <form wire:submit="create" class="flex flex-wrap items-start gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="min-w-0 flex-1">
                <label for="projectName" class="sr-only">{{ __('panel.common.name') }}</label>
                <input id="projectName" type="text" wire:model="name" placeholder="{{ __('panel.common.name') }}" maxlength="255"
                       class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.create') }}</button>
            <button type="button" wire:click="cancelCreate" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
        </form>
    @endif

    @if ($projects->isEmpty() && ! $showCreateForm)
        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">
            {{ __('panel.projects.empty') }}
        </p>
    @endif

    {{-- Skeleton durante ações Livewire (criar/editar/excluir): mostra a
         ESTRUTURA da lista em vez de spinner — percepção de rapidez. --}}
    <div wire:loading class="space-y-3">
        <x-skeleton :lines="3" />
    </div>

    <div wire:loading.remove class="space-y-3">
        @foreach ($projects as $project)
            <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @if ($editingUuid === $project->uuid)
                    {{-- Edição inline --}}
                    <form wire:submit="update" class="flex flex-wrap items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="editingName" class="sr-only">{{ __('panel.projects.edit') }}</label>
                            <input id="editingName" type="text" wire:model="editingName" maxlength="255"
                                   class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                            @error('editingName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.save') }}</button>
                        <button type="button" wire:click="cancelEdit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                    </form>
                @elseif ($confirmingDeleteUuid === $project->uuid)
                    {{-- Confirmação de exclusão inline --}}
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('panel.projects.delete_warning', ['name' => $project->name]) }}</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="delete" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white">{{ __('panel.common.delete') }}</button>
                        <button type="button" wire:click="cancelDelete" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700">{{ __('panel.common.cancel') }}</button>
                    </div>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ $project->name }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <code>{{ $project->codigo_publico }}</code>
                                · {{ __('panel.projects.linked_keys', ['count' => $project->api_keys_count]) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="startEdit('{{ $project->uuid }}')"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium dark:border-gray-700">{{ __('panel.common.edit') }}</button>
                            <button type="button" wire:click="startDelete('{{ $project->uuid }}')"
                                    class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 dark:border-red-800 dark:text-red-400">{{ __('panel.common.delete') }}</button>
                        </div>
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</div>
