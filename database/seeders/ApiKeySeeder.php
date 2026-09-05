<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * Chaves de API de demonstração (ADR-006).
 *
 * SEGURANÇA (a parte que importa neste arquivo): nenhuma chave secreta é
 * gerada, gravada ou exibida. A coluna `secret_hash` recebe um hash
 * DETERMINÍSTICO de uma string de semente — não existe `sk_` correspondente,
 * portanto nenhuma destas chaves autentica coisa alguma. Elas existem para
 * encher a listagem e os dashboards, não para serem usadas.
 *
 * Variedade proposital de status (ativa, revogada, expirada, rotacionada) e
 * de datas (até 200 dias) para que os filtros do painel e a comparação de
 * período dos dashboards tenham o que mostrar.
 *
 * IDEMPOTENTE: uuid derivado do índice (UUID v5 com semente fixa).
 */
final class ApiKeySeeder extends Seeder
{
    public const QUANTIDADE = 24;

    public function run(): void
    {
        $usuarios = User::query()->orderBy('id')->pluck('id')->all();

        if ($usuarios === []) {
            return;
        }

        $faker = FakerFactory::create('pt_BR');
        $faker->seed(DashboardHistorySeeder::SEMENTE);

        $projetosPorDono = Project::query()->get(['id', 'user_id'])->groupBy('user_id');

        for ($indice = 0; $indice < self::QUANTIDADE; $indice++) {
            $uuid = (string) Uuid::uuid5(DashboardHistorySeeder::NAMESPACE_UUID, 'api-key-seed-'.$indice);

            $nome = ucfirst($faker->words(2, true)).' API';
            // Distribuição ENVIESADA para o presente (o quadrado de um
            // sorteio uniforme): uma conta real acumula mais registros
            // recentes do que antigos, e é isso que faz o período anterior
            // ter base de comparação em vez de zero.
            $dias = (int) round(DashboardHistorySeeder::DIAS * ($faker->randomFloat(4, 0, 1) ** 2));
            $dono = $faker->randomElement($usuarios);
            $usadaHa = $faker->numberBetween(0, 20);
            $hora = $faker->numberBetween(8, 21);
            $minuto = $faker->numberBetween(0, 59);

            // 1 em 6 revogada, 1 em 9 expirada, 1 em 11 rotacionada.
            $status = match (true) {
                $indice % 6 === 4 => ApiKeyStatus::Revoked,
                $indice % 9 === 7 => ApiKeyStatus::Expired,
                $indice % 11 === 9 => ApiKeyStatus::Rotated,
                default => ApiKeyStatus::Active,
            };

            if (ApiKey::query()->where('uuid', $uuid)->exists()) {
                continue;
            }

            $criadaEm = now()->subDays($dias)->setTime($hora, $minuto);

            if ($criadaEm->isFuture()) {
                $criadaEm = now();
            }

            $chave = new ApiKey;

            $chave->forceFill([
                'uuid' => $uuid,
                'user_id' => $dono,
                'name' => $nome,
                // Chave PÚBLICA determinística (identificador, não segredo).
                'public_key' => 'pk_test_'.substr(hash('sha256', 'api-key-public-'.$indice), 0, 32),
                // Hash de uma secreta que NUNCA existiu: a chave é inerte.
                'secret_hash' => hash('sha256', 'api-key-demo-inerte-'.$indice),
                'scopes' => config('api_keys.default_scopes', ['*:*']),
                'status' => $status,
                'expires_at' => $status === ApiKeyStatus::Expired ? $criadaEm->copy()->addDays(30) : null,
                'last_used_at' => $status === ApiKeyStatus::Active ? now()->subDays($usadaHa) : null,
                'created_at' => $criadaEm,
                'updated_at' => $criadaEm,
            ])->save();

            // Vínculo com um projeto do MESMO dono, quando houver: chave sem
            // vínculo enxerga a conta inteira (ADR-005/006) — as duas
            // situações aparecem na demo.
            $projetos = $projetosPorDono->get($dono);

            if ($projetos !== null && $indice % 3 !== 0) {
                $chave->projects()->sync([$projetos->first()->id]);
            }
        }
    }
}
