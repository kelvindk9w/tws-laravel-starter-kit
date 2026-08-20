<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Dashboard do painel do usuário (Fase 6 — ADR-005/011): boas-vindas,
 * dados do usuário e resumo da conta. Esqueleto — os blocos de cobranças,
 * saldo e webhooks entram com o motor de pagamentos (gatPay).
 */
final class Dashboard extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.dashboard', [
            'user' => $user,
            'activeKeysCount' => ApiKey::query()
                ->where('user_id', $user->id)
                ->where('status', ApiKeyStatus::Active)
                ->count(),
            'projectsCount' => Project::query()
                ->where('user_id', $user->id)
                ->count(),
        ])->title(__('panel.dashboard.title'));
    }
}
