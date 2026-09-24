<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\Tenancy\Queries\AccountOverviewQuery;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Dashboard do painel do usuário (Livewire).
 *
 * A tela de entrada mostra o que o kit JÁ COLETA sobre a conta: chaves,
 * projetos e o tráfego real da API do tenant (request_logs). Um dashboard que
 * só conta linhas de duas tabelas não prova que a instrumentação existe.
 *
 * Os números e listas vêm do AccountOverviewQuery (backend, reutilizável por
 * qualquer tela); este componente só os apresenta.
 */
final class Dashboard extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.dashboard', [
            'user' => $user,
            ...app(AccountOverviewQuery::class)->for($user)->toArray(),
        ])->title(__('panel.dashboard.title'));
    }
}
