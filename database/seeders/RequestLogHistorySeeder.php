<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Logging\Redactor;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * O passado ANTERIOR aos 30 dias do RequestLogSeeder (que este arquivo NÃO
 * altera): do dia 31 ao dia 200.
 *
 * Motivo: o seletor de período dos dashboards vai até 90 dias, e a comparação
 * com o período anterior precisa de mais 90. Com só 30 dias semeados, a janela
 * de 90 dias mostrava dois terços de gráfico vazio e todo card dizia "sem base
 * de comparação" — o oposto da primeira impressão que o painel deve dar.
 *
 * Segue as MESMAS regras do módulo de logs (ADR-004/005), sem exceção:
 * - APPEND-ONLY: só INSERT, nunca update/delete (o model os proíbe);
 * - REDACTION: o payload passa pelo mesmo Redactor do middleware;
 * - IDEMPOTÊNCIA por correlation_id determinístico (UUID v5), num namespace
 *   próprio para nunca colidir com as linhas do RequestLogSeeder.
 *
 * O volume é MENOR que o dos 30 dias recentes, de propósito: a plataforma da
 * demo aparece crescendo, não estagnada.
 */
final class RequestLogHistorySeeder extends Seeder
{
    /**
     * Primeiro dia semeado aqui (o RequestLogSeeder cobre 0..29).
     */
    public const PRIMEIRO_DIA = RequestLogSeeder::DIAS;

    public const ULTIMO_DIA = DashboardHistorySeeder::DIAS;

    /**
     * Volume do dia — função pura da data (mesma decisão comentada no
     * SubmissionHistorySeeder). Cresce à medida que se aproxima do presente:
     * a plataforma da demo aparece crescendo, não estagnada.
     */
    private static function volumeDoDia(Carbon $data, int $dia): int
    {
        $semente = crc32($data->toDateString().'-request-log');

        $tendencia = (int) floor((self::ULTIMO_DIA - $dia) / 25);

        return $data->isWeekend()
            ? 3 + ($semente % (5 + $tendencia))
            : 8 + ($semente % (9 + $tendencia));
    }

    public function run(): void
    {
        $faker = FakerFactory::create('pt_BR');
        $faker->seed(DashboardHistorySeeder::SEMENTE);

        $redactor = app(Redactor::class);

        $tenantUuid = User::query()
            ->where('email', config('ui.demo_login.email'))
            ->value('uuid');

        $endpoints = [
            ['GET', 'api/v1/projects'],
            ['POST', 'api/v1/projects'],
            ['GET', 'api/v1/api-keys'],
            ['POST', 'api/v1/uploads'],
            ['GET', 'dashboard'],
            ['GET', 'api-keys'],
            ['POST', 'login'],
            ['GET', 'admin/users'],
            ['GET', 'admin/request-logs'],
        ];

        $indice = 0;

        for ($dia = self::ULTIMO_DIA; $dia >= self::PRIMEIRO_DIA; $dia--) {
            $data = now()->subDays($dia);

            // Volume cresce à medida que se aproxima do presente (o dia 200
            // é o mais fraco), com fim de semana mais calmo.
            $volume = self::volumeDoDia($data, $dia);

            for ($i = 0; $i < $volume; $i++) {
                [$metodo, $endpoint] = $faker->randomElement($endpoints);

                $sorteio = $faker->numberBetween(1, 100);

                [$status, $http] = match (true) {
                    $sorteio <= 3 => [RequestLogStatus::Bloqueada, 422],
                    $sorteio <= 7 => [RequestLogStatus::Erro, 500],
                    $sorteio <= 22 => [RequestLogStatus::Concluida, $faker->randomElement([401, 403, 404, 422])],
                    default => [RequestLogStatus::Concluida, $faker->randomElement([200, 200, 200, 201, 204])],
                };

                $ip = $faker->ipv4();
                $agente = $faker->userAgent();
                $duracao = $faker->numberBetween(8, 1800);
                $hora = $faker->numberBetween(0, 23);
                $minuto = $faker->numberBetween(0, 59);
                $ataque = $status === RequestLogStatus::Bloqueada
                    ? $faker->randomElement(['xss', 'sqli', 'path_traversal'])
                    : null;

                $correlationId = (string) Uuid::uuid5(
                    DashboardHistorySeeder::NAMESPACE_UUID,
                    'request-log-history-'.$indice,
                );

                $indice++;

                if (RequestLog::query()->where('correlation_id', $correlationId)->exists()) {
                    continue;
                }

                // MESMA redaction do middleware (LGPD — ADR-004).
                $payload = $redactor->redactArray([
                    'query' => [],
                    'body' => $metodo === 'GET' ? [] : ['exemplo' => 'seed'],
                ]);

                (new RequestLog)->forceFill([
                    'correlation_id' => $correlationId,
                    'tenant_uuid' => str_starts_with($endpoint, 'api/v1/') ? $tenantUuid : null,
                    'ip' => $ip,
                    'user_agent' => $agente,
                    'method' => $metodo,
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                    'status' => $status,
                    'attack_type' => $ataque,
                    'http_status_response' => $http,
                    'duration_ms' => $duracao,
                    'created_at' => $data->copy()->setTime($hora, $minuto),
                ])->save();
            }
        }
    }
}
