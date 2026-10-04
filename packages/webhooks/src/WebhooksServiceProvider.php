<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Twstec\Kit\Foundation\Localization\PackageTranslations;
use Twstec\Kit\Webhooks\Console\DispatchPendingDeliveries;
use Twstec\Kit\Webhooks\Console\PruneWebhookEvents;
use Twstec\Kit\Webhooks\Security\DestinationPolicy;
use Twstec\Kit\Webhooks\Security\HostResolver;
use Twstec\Kit\Webhooks\Security\SystemHostResolver;
use Twstec\Kit\Webhooks\Support\WebhookLifecycle;

/**
 * O que o pacote de webhooks instala numa aplicação Laravel — sozinho:
 *
 * - a configuração padrão (`config('webhooks')`), a migration das quatro
 *   tabelas, as traduções (`webhooks.*`, o aplicativo vence na mesma chave) e
 *   a view do e-mail de aviso (`webhooks::mail.endpoint-disabled`);
 * - o resolvedor de DNS do sistema (Security\HostResolver — `bindIf`: o
 *   aplicativo, ou a suíte, pode trocar);
 * - a EXCLUSÃO com a conta e a pessoa (Support\WebhookLifecycle — sem opção
 *   para desligar);
 * - os comandos `webhooks:dispatch-pending` (o outbox de volta à fila) e
 *   `webhooks:prune` (retenção) e o AGENDAMENTO deles (cron vazio desliga,
 *   com aviso no log a cada boot);
 * - os AVISOS de configuração no log a cada boot (HTTPS desligado ou rede
 *   privada liberada em produção — ignorados lá; faixa inválida).
 *
 * Telas não há: os starters trazem as deles, sobre as Actions do pacote.
 */
final class WebhooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/webhooks.php'), 'webhooks');

        PackageTranslations::register($this->app, $this->path('lang'));

        $this->app->bindIf(HostResolver::class, SystemHostResolver::class);
    }

    public function boot(): void
    {
        foreach ($this->app->make(DestinationPolicy::class)->warnings() as $warning) {
            Log::warning($warning);
        }

        WebhookLifecycle::register($this->app['events']);

        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'webhooks');

        $this->schedule('webhooks:dispatch-pending', 'webhooks.outbox.schedule', 'webhooks.outbox.schedule vazio: o outbox dos webhooks (webhooks:dispatch-pending) está DESLIGADO. Entrega que não chegou à fila (fila fora do ar) ou cujo job se perdeu só sai rodando o comando à mão.');
        $this->schedule('webhooks:prune', 'webhooks.prune.schedule', 'webhooks.prune.schedule vazio: a limpeza dos eventos antigos de webhook (webhooks:prune) está DESLIGADA. Os eventos (com o corpo cifrado) e o log de entregas ficam guardados até rodar o comando à mão.');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchPendingDeliveries::class,
                PruneWebhookEvents::class,
            ]);

            $this->publishes([
                $this->path('config/webhooks.php') => config_path('webhooks.php'),
            ], 'webhooks-config');
        }
    }

    private function schedule(string $command, string $configKey, string $warning): void
    {
        $cron = trim((string) config($configKey, ''));

        if ($cron === '' || in_array(strtolower($cron), ['false', 'off', '0'], true)) {
            Log::warning($warning);

            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($command, $cron): void {
            $schedule->command($command)
                ->cron($cron)
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
