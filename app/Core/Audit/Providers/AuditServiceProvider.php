<?php

declare(strict_types=1);

namespace App\Core\Audit\Providers;

use App\Core\Audit\AuditTrail;
use App\Core\Audit\Console\PruneAuditEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Trilha de auditoria de ações: o serviço (um por requisição/processo, que
 * guarda o escopo aberto), o ouvinte dos eventos Eloquent e o comando de
 * poda.
 *
 * O ouvinte é global, mas só grava com um AuditScope aberto — fora de um
 * ponto de entrada auditado, uma gravação de model continua custando só uma
 * checagem de nulo.
 */
final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuditTrail::class);
    }

    public function boot(): void
    {
        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'],
            function (string $eventName, array $payload): void {
                $trail = $this->app->make(AuditTrail::class);

                if ($trail->current() === null) {
                    return;
                }

                $model = $payload[0] ?? null;

                if ($model instanceof Model) {
                    // "eloquent.updated: App\Models\X" → "updated"
                    $trail->modelEvent(substr($eventName, 9, (int) strpos($eventName, ':') - 9), $model);
                }
            },
        );

        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditEvents::class]);
        }
    }
}
