<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Logging\Redactor;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * ~30 dias de request_logs realistas: sem eles o gráfico do dashboard do
 * /admin nasce vazio em toda instalação nova e a tela de auditoria não tem
 * o que filtrar.
 *
 * Compatível com as regras do módulo de logs (ADR-004/005):
 * - APPEND-ONLY: só INSERT. Nada de update/delete (o model os proíbe), e
 *   por isso a idempotência é por firstOrCreate com correlation_id
 *   DETERMINÍSTICO (mesma semente ⇒ mesmas linhas ⇒ nenhuma duplicata);
 * - REDACTION: o payload passa pelo MESMO Redactor do middleware antes de
 *   ser persistido — o seeder não é uma porta lateral para gravar dado
 *   sensível cru;
 * - a distribuição imita tráfego real: maioria CONCLUIDA, uma fatia de 4xx,
 *   pouquíssimos 5xx (ERRO) e algumas tentativas BLOQUEADA.
 */
class RequestLogSeeder extends Seeder
{
    /**
     * Janela semeada (o gráfico do dashboard mostra 30 dias).
     */
    public const DIAS = 30;

    /**
     * Semente fixa: o que torna o seeder idempotente.
     */
    private const SEMENTE = 20260904;

    /**
     * Namespace dos correlation_id determinísticos (UUID v5).
     */
    private const NAMESPACE_UUID = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function run(): void
    {
        $faker = FakerFactory::create('pt_BR');
        $faker->seed(self::SEMENTE);

        $redactor = app(Redactor::class);

        // Tenant das linhas de API/painel: o usuário demo. Sem isso o
        // dashboard do PAINEL (que filtra por tenant_uuid) nasceria vazio em
        // instalação nova, mesmo com o do /admin cheio. Rotas de admin e de
        // login ficam sem tenant — é assim que o middleware as grava.
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

        $linhas = [];
        $indice = 0;

        for ($dia = self::DIAS - 1; $dia >= 0; $dia--) {
            // Volume que varia por dia (fim de semana mais fraco) para o
            // gráfico ter forma em vez de uma reta.
            $data = now()->subDays($dia);
            $volume = $data->isWeekend()
                ? $faker->numberBetween(6, 14)
                : $faker->numberBetween(18, 40);

            for ($i = 0; $i < $volume; $i++) {
                [$metodo, $endpoint] = $faker->randomElement($endpoints);

                $sorteio = $faker->numberBetween(1, 100);

                [$status, $http] = match (true) {
                    $sorteio <= 3 => [RequestLogStatus::Bloqueada, 422],
                    $sorteio <= 6 => [RequestLogStatus::Erro, 500],
                    $sorteio <= 20 => [RequestLogStatus::Concluida, $faker->randomElement([401, 403, 404, 422])],
                    default => [RequestLogStatus::Concluida, $faker->randomElement([200, 200, 200, 201, 204])],
                };

                $linhas[] = [
                    'indice' => $indice++,
                    'metodo' => $metodo,
                    'endpoint' => $endpoint,
                    'status' => $status,
                    'http' => $http,
                    'ip' => $faker->ipv4(),
                    'agente' => $faker->userAgent(),
                    'duracao' => $faker->numberBetween(8, 1800),
                    'criado_em' => $data->copy()->setTime(
                        $faker->numberBetween(0, 23),
                        $faker->numberBetween(0, 59),
                        $faker->numberBetween(0, 59),
                    ),
                    'ataque' => $status === RequestLogStatus::Bloqueada
                        ? $faker->randomElement(['xss', 'sqli', 'path_traversal'])
                        : null,
                    'tenant' => (str_starts_with($endpoint, 'api/v1/') || in_array($endpoint, ['dashboard', 'api-keys'], true))
                        ? $tenantUuid
                        : null,
                ];
            }
        }

        foreach ($linhas as $linha) {
            $correlationId = $this->correlationIdDeterministico($linha['indice']);

            if (RequestLog::query()->where('correlation_id', $correlationId)->exists()) {
                continue;
            }

            // MESMA redaction do middleware (LGPD — ADR-004).
            $payload = $redactor->redactArray([
                'query' => [],
                'body' => $linha['metodo'] === 'GET' ? [] : ['exemplo' => 'seed'],
            ]);

            $log = new RequestLog;

            $log->forceFill([
                'correlation_id' => $correlationId,
                'tenant_uuid' => $linha['tenant'],
                'ip' => $linha['ip'],
                'user_agent' => $linha['agente'],
                'method' => $linha['metodo'],
                'endpoint' => $linha['endpoint'],
                'payload' => $payload,
                'status' => $linha['status'],
                'attack_type' => $linha['ataque'],
                'http_status_response' => $linha['http'],
                'duration_ms' => $linha['duracao'],
                'created_at' => $linha['criado_em'],
            ])->save();
        }
    }

    /**
     * UUID v5 (determinístico) a partir do índice da linha — é ele que
     * garante a idempotência sem precisar de UPDATE/DELETE.
     */
    private function correlationIdDeterministico(int $indice): string
    {
        return (string) Uuid::uuid5(self::NAMESPACE_UUID, 'request-log-seed-'.$indice);
    }
}
