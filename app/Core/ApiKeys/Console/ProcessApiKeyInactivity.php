<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Console;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Mail\ApiKeyInactivityWarningMail;
use App\Core\ApiKeys\Models\ApiKey;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job diário de expiração por INATIVIDADE (ADR-006 — scheduler, ver
 * routes/console.php). Dois passes, sempre em UTC:
 *
 * 1. AVISO PRÉVIO: chave ativa cuja última atividade (last_used_at ou
 *    criação) entrou na janela de aviso (X meses − Y dias sem uso) recebe
 *    e-mail de aviso UMA única vez por ciclo — a flag
 *    inactivity_warning_sent_at impede repetição (e é rearmada quando a
 *    chave volta a ser usada — ver ApiKey::touchLastUsedThrottled()).
 * 2. DESATIVAÇÃO: chave ativa sem atividade há X meses vira
 *    expired_inactivity (irreversível pelo usuário — cria-se/rotaciona-se).
 *
 * Limites vêm de config/api_keys.php (API_KEYS_INACTIVITY_*) — ADR-007.
 */
final class ProcessApiKeyInactivity extends Command
{
    protected $signature = 'api-keys:process-inactivity';

    protected $description = 'Avisa (e-mail) e desativa chaves de API inativas há mais que o limite configurado (ADR-006).';

    public function handle(): int
    {
        /** @var array{enabled: bool, months: int, warning_days: int} $config */
        $config = config('api_keys.inactivity');

        if (! $config['enabled']) {
            $this->info('Expiração por inatividade desabilitada (API_KEYS_INACTIVITY_ENABLED=false).');

            return self::SUCCESS;
        }

        // subMonthsNoOverflow: evita salto de mês em datas como 31/08.
        $expireBefore = now()->subMonthsNoOverflow($config['months']);
        $warnBefore = now()->subMonthsNoOverflow($config['months'])->addDays($config['warning_days']);

        $warned = $this->sendWarnings($warnBefore, $expireBefore, $config['warning_days']);
        $expired = $this->expireInactive($expireBefore);

        $this->info("Avisos de inatividade enviados: {$warned}. Chaves desativadas: {$expired}.");

        Log::channel('request_log')->info('api_keys.inactivity.processed', [
            'warned' => $warned,
            'expired' => $expired,
        ]);

        return self::SUCCESS;
    }

    /**
     * Passa 1 — e-mail de aviso prévio (uma única vez por ciclo).
     */
    private function sendWarnings(CarbonInterface $warnBefore, CarbonInterface $expireBefore, int $warningDays): int
    {
        $warned = 0;

        ApiKey::query()
            ->where('status', ApiKeyStatus::Active)
            ->whereNull('inactivity_warning_sent_at')
            ->where(fn (Builder $query) => $this->lastActivityBetween($query, $expireBefore, $warnBefore))
            ->with('owner')
            ->chunkById(100, function ($keys) use (&$warned, $warningDays): void {
                foreach ($keys as $key) {
                    /** @var ApiKey $key */
                    if ($key->owner === null) {
                        continue;
                    }

                    // Locale do DESTINATÁRIO (ADR-007): o aviso sai no idioma
                    // preferido do usuário, não no locale da requisição CLI.
                    Mail::to($key->owner)
                        ->locale($key->owner->preferredLocale())
                        ->queue(new ApiKeyInactivityWarningMail($key, $warningDays));

                    $key->forceFill(['inactivity_warning_sent_at' => now()])->save();

                    $warned++;
                }
            });

        return $warned;
    }

    /**
     * Passa 2 — desativação das chaves que cruzaram o limite de inatividade.
     */
    private function expireInactive(CarbonInterface $expireBefore): int
    {
        $expired = 0;

        ApiKey::query()
            ->where('status', ApiKeyStatus::Active)
            ->where(fn (Builder $query) => $this->lastActivityBefore($query, $expireBefore))
            ->chunkById(100, function ($keys) use (&$expired): void {
                foreach ($keys as $key) {
                    /** @var ApiKey $key */
                    $key->forceFill(['status' => ApiKeyStatus::ExpiredInactivity])->save();

                    $expired++;
                }
            });

        return $expired;
    }

    /**
     * Última atividade (last_used_at ?? created_at) anterior ao limite —
     * somente bindings, portável (sqlite/pgsql), sem concatenação (item 7).
     */
    private function lastActivityBefore(Builder $query, CarbonInterface $limit): void
    {
        $query->where(function (Builder $q) use ($limit): void {
            $q->whereNotNull('last_used_at')->where('last_used_at', '<', $limit);
        })->orWhere(function (Builder $q) use ($limit): void {
            $q->whereNull('last_used_at')->where('created_at', '<', $limit);
        });
    }

    /**
     * Última atividade dentro da janela de aviso: já entrou no período de
     * aviso (mais antiga que warnBefore) mas AINDA não cruzou a expiração.
     */
    private function lastActivityBetween(Builder $query, CarbonInterface $lowerBound, CarbonInterface $upperBound): void
    {
        $query->where(function (Builder $q) use ($lowerBound, $upperBound): void {
            $q->whereNotNull('last_used_at')
                ->where('last_used_at', '<', $upperBound)
                ->where('last_used_at', '>=', $lowerBound);
        })->orWhere(function (Builder $q) use ($lowerBound, $upperBound): void {
            $q->whereNull('last_used_at')
                ->where('created_at', '<', $upperBound)
                ->where('created_at', '>=', $lowerBound);
        });
    }
}
