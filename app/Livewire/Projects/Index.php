<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Projetos — CRUD só com nome, TUDO na mesma tela (ADR-005): criar e editar
 * inline, excluir com confirmação inline. Sem labirinto de cliques.
 *
 * Consome o mesmo model/invariantes da API v1 (Fase 4): createWithPublicCodeRetry,
 * isolamento por dono (uuid de outro tenant = 404) e regras do StoreProjectRequest.
 */
final class Index extends Component
{
    /** Form inline de criação. */
    public bool $showCreateForm = false;

    public string $name = '';

    /** Edição inline: uuid do projeto sendo editado (null = nenhum). */
    public ?string $editingUuid = null;

    public string $editingName = '';

    /** Exclusão: uuid aguardando confirmação (null = nenhum). */
    public ?string $confirmingDeleteUuid = null;

    /**
     * @return Collection<int, Project>
     */
    public function projects(): Collection
    {
        return Project::query()
            ->where('user_id', $this->user()->id)
            ->withCount('apiKeys')
            ->latest()
            ->get();
    }

    public function startCreate(): void
    {
        $this->resetValidation();
        $this->reset('name');
        $this->showCreateForm = true;
    }

    public function cancelCreate(): void
    {
        $this->showCreateForm = false;
        $this->reset('name');
    }

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255']], [], [
            'name' => __('panel.common.name'),
        ]);

        Project::createWithPublicCodeRetry([
            'user_id' => $this->user()->id,
            'name' => $this->name,
        ]);

        $this->cancelCreate();
        session()->flash('projects_status', __('panel.projects.created'));
    }

    public function startEdit(string $uuid): void
    {
        $project = $this->findOwned($uuid);

        $this->resetValidation();
        $this->editingUuid = $uuid;
        $this->editingName = (string) $project->name;
        $this->showCreateForm = false;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingUuid', 'editingName');
    }

    public function update(): void
    {
        $this->validate(['editingName' => ['required', 'string', 'max:255']], [], [
            'editingName' => __('panel.common.name'),
        ]);

        $project = $this->findOwned((string) $this->editingUuid);
        $project->update(['name' => $this->editingName]);

        $this->cancelEdit();
        session()->flash('projects_status', __('panel.projects.updated'));
    }

    public function startDelete(string $uuid): void
    {
        $this->findOwned($uuid);
        $this->confirmingDeleteUuid = $uuid;
    }

    public function cancelDelete(): void
    {
        $this->reset('confirmingDeleteUuid');
    }

    /**
     * Exclui o projeto em confirmação.
     *
     * O nome NÃO pode ser `delete`: o Livewire 4 em modo CSP-safe compila a
     * expressão de `wire:click` com um parser de JS, e `delete` é PALAVRA
     * RESERVADA da linguagem (operador). O resultado era um erro de parser no
     * console e a exclusão nunca acontecia — a ação parecia inerte. Toda ação
     * Livewire do kit evita nomes reservados do JavaScript (delete, new,
     * class, default, typeof, in, …).
     */
    public function removeProject(): void
    {
        $project = $this->findOwned((string) $this->confirmingDeleteUuid);

        // O vínculo N:N cai junto (cascadeOnDelete na pivot — Fase 4); chaves
        // que só atendiam este projeto voltam a enxergar a conta toda.
        $project->delete();

        $this->cancelDelete();
        session()->flash('projects_status', __('panel.projects.deleted'));
    }

    public function render(): View
    {
        return view('livewire.projects.index', [
            'projects' => $this->projects(),
        ])->title(__('panel.projects.title'));
    }

    /**
     * Projeto do PRÓPRIO usuário por UUID — de outro tenant = 404 (checklist 11/31).
     */
    private function findOwned(string $uuid): Project
    {
        /** @var Project */
        return Project::query()
            ->where('user_id', $this->user()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
