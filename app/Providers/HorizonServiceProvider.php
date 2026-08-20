<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Auth\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon (Fase 7 — ADR-010): supervisor de filas + dashboard /horizon.
 *
 * Acesso ao dashboard: SÓ super admin (is_admin), mesmo critério do painel
 * Filament (ADR-011). A IP allowlist do admin (EnsureAdminIpAllowed) também
 * se aplica às rotas do Horizon — ver config/horizon.php → middleware.
 * Em ambiente local o Horizon libera o acesso sem gate (comportamento
 * padrão do pacote, apenas desenvolvimento).
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => (bool) $user?->is_admin);
    }
}
