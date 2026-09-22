<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Auth\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon (Fase 7 — ADR-010): supervisor de filas + dashboard /horizon.
 *
 * Acesso ao dashboard: SÓ super admin com CONTA ATIVA, o mesmo critério do
 * painel Filament (User::canAccessPanel — ADR-011). A barreira de origem
 * (EnsureAdminIpAllowed) também se aplica às rotas do Horizon — ver
 * config/horizon.php → middleware. Em ambiente local o pacote libera o acesso
 * sem gate (comportamento padrão dele, apenas desenvolvimento).
 *
 * POR QUE O GATE CHECA O STATUS DA CONTA, e não só a flag: o gate antes olhava
 * apenas `is_admin`. Desativar ou bloquear a conta de um administrador tirava o
 * acesso dele ao /admin (o Filament consulta canAccessPanel a cada requisição)
 * e NÃO tirava o acesso ao /horizon — com a sessão ainda viva, o administrador
 * recém-desativado continuava enxergando e operando a fila: retry de job,
 * payload de job falho, métricas. Revogar acesso tem de revogar em todas as
 * superfícies, ou não é revogação. Os dois pontos passam a ler o MESMO
 * critério, que é o que impede que voltem a divergir.
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
        Gate::define(
            'viewHorizon',
            fn (?User $user = null): bool => (bool) $user?->is_admin && (bool) $user?->isActive(),
        );
    }
}
