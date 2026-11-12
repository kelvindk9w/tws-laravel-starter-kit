<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Twstec\Kit\Foundation\Customization\PackageConfig;
use Twstec\Kit\Foundation\Localization\PackageTranslations;
use Twstec\Kit\Foundation\Security\SignedBodyPaths;
use Twstec\Kit\Webhooks\Console\DispatchPendingDeliveries;
use Twstec\Kit\Webhooks\Console\PruneWebhookEvents;
use Twstec\Kit\Webhooks\Inbound\Console\PruneInboundWebhooks;
use Twstec\Kit\Webhooks\Inbound\Console\RequeueInboundWebhooks;
use Twstec\Kit\Webhooks\Inbound\Http\ReceiveInboundWebhook;
use Twstec\Kit\Webhooks\Inbound\InboundSources;
use Twstec\Kit\Webhooks\Inbound\InboundWebhooks;
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
 *   privada liberada em produção — ignorados lá; faixa inválida);
 * - os webhooks RECEBIDOS (registerInbound): o registro das fontes, a rota
 *   `POST /<webhooks.inbound.path>/{source}`, o limitador
 *   `webhooks-inbound`, o caminho nos caminhos de corpo assinado do filtro
 *   de ataques e a retenção `webhooks:prune-inbound` (agendada).
 *
 * Telas não há: os starters trazem as deles, sobre as Actions do pacote.
 */
final class WebhooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        PackageConfig::merge($this->app, $this->path('config/webhooks.php'), 'webhooks');

        PackageTranslations::register($this->app, $this->path('lang'));

        $this->app->bindIf(HostResolver::class, SystemHostResolver::class);

        $this->app->singleton(InboundSources::class);

        // Depois do boot: a configuração já é a final (a do aplicativo, a do
        // ambiente de teste) e o `config:cache` grava o resultado.
        $this->app->booted(fn () => $this->contributeInboundPath());
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

        $this->registerInbound();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchPendingDeliveries::class,
                PruneWebhookEvents::class,
                PruneInboundWebhooks::class,
                RequeueInboundWebhooks::class,
            ]);

            $this->publishes([
                $this->path('config/webhooks.php') => config_path('webhooks.php'),
            ], 'webhooks-config');
        }
    }

    /**
     * O caminho dos webhooks recebidos nos caminhos de CORPO ASSINADO do
     * filtro de ataques (`security.validation.signed_body_paths`): ali o
     * corpo não é inspecionado (texto de terceiros recusado faria o provedor
     * reenviar e, por fim, descartar o evento) e NUNCA vai para a trilha
     * (request_logs) — só o tamanho e o formato. Vale porque a rota recusa
     * todo corpo sem verificação válida antes de qualquer uso.
     *
     * Entra também quando o aplicativo escreveu a chave `validation` dele
     * (`intoApplicationKeys`): o corpo do webhook não pode chegar à trilha
     * por uma cópia de configuração.
     */
    private function contributeInboundPath(): void
    {
        if (! (bool) config('webhooks.inbound.enabled', true)) {
            return;
        }

        $pattern = InboundWebhooks::path().'/*';

        if (! SignedBodyPaths::isAcceptable($pattern)) {
            return;
        }

        PackageConfig::contribute($this->app, 'security.validation.signed_body_paths', [$pattern], intoApplicationKeys: true);
    }

    /**
     * A rota, o limitador e a retenção dos webhooks recebidos.
     */
    private function registerInbound(): void
    {
        if (! (bool) config('webhooks.inbound.enabled', true)) {
            return;
        }

        if (! SignedBodyPaths::isAcceptable(InboundWebhooks::path().'/*')) {
            Log::warning('webhooks.inbound.path inválido (use só segmentos literais, sem *): a rota dos webhooks recebidos NÃO foi registrada.');

            return;
        }

        // Por fonte e IP; o limite é o da fonte (sem montar o verificador).
        RateLimiter::for(ReceiveInboundWebhook::LIMITER, function (Request $request): Limit {
            $name = (string) $request->route('source');

            return Limit::perMinute($this->app->make(InboundSources::class)->rateLimitFor($name))->by($name.'|'.$request->ip());
        });

        $this->loadRoutesFrom($this->path('routes/inbound.php'));

        $this->schedule('webhooks:requeue-inbound', 'webhooks.inbound.requeue.schedule', 'webhooks.inbound.requeue.schedule vazio: a rodada que põe na fila os webhooks recebidos com a fila fora do ar (webhooks:requeue-inbound) está DESLIGADA. Esses avisos ficam `received` até rodar o comando à mão.');
        $this->schedule('webhooks:prune-inbound', 'webhooks.inbound.retention.schedule', 'webhooks.inbound.retention.schedule vazio: a limpeza dos webhooks recebidos (webhooks:prune-inbound) está DESLIGADA. Os registros de deduplicação e o corpo guardado (store_payload) ficam até rodar o comando à mão.');
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
