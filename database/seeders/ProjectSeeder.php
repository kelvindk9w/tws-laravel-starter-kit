<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Enums\ProjectStatus;
use App\Core\Tenancy\Models\Project;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

/**
 * Projetos de demonstração (camada organizacional da conta — ADR-005).
 *
 * Sem eles, o card "Projetos" e a tabela "Projetos recentes" dos dashboards
 * nascem zerados em toda instalação nova. Os projetos ficam espalhados no
 * tempo (até 200 dias) para que a comparação com o período anterior tenha
 * base em qualquer janela do seletor.
 *
 * IDEMPOTENTE: o uuid de cada projeto é derivado do índice (UUID v5 com
 * semente fixa) — a segunda execução não cria nada.
 */
final class ProjectSeeder extends Seeder
{
    public const QUANTIDADE = 28;

    public function run(): void
    {
        $usuarios = User::query()->orderBy('id')->pluck('id')->all();

        if ($usuarios === []) {
            return;
        }

        $faker = FakerFactory::create('pt_BR');
        $faker->seed(DashboardHistorySeeder::SEMENTE);

        for ($indice = 0; $indice < self::QUANTIDADE; $indice++) {
            $uuid = (string) Uuid::uuid5(DashboardHistorySeeder::NAMESPACE_UUID, 'project-seed-'.$indice);

            // Sorteio SEMPRE feito (mesmo quando a linha já existe), para que
            // a sequência do Faker não dependa do estado do banco — é o que
            // mantém a idempotência real, e não só a ausência de duplicata.
            $nome = ucfirst($faker->words(2, true));
            // Distribuição ENVIESADA para o presente (o quadrado de um
            // sorteio uniforme): uma conta real acumula mais registros
            // recentes do que antigos, e é isso que faz o período anterior
            // ter base de comparação em vez de zero.
            $dias = (int) round(DashboardHistorySeeder::DIAS * ($faker->randomFloat(4, 0, 1) ** 2));
            $dono = $faker->randomElement($usuarios);
            $arquivado = $indice % 7 === 3;
            $hora = $faker->numberBetween(8, 21);
            $minuto = $faker->numberBetween(0, 59);

            if (Project::query()->where('uuid', $uuid)->exists()) {
                continue;
            }

            $criadoEm = now()->subDays($dias)->setTime($hora, $minuto);

            // Nunca no futuro: o dia 0 com hora sorteada em 21h passaria de agora.
            if ($criadoEm->isFuture()) {
                $criadoEm = now();
            }

            (new Project)->forceFill([
                'uuid' => $uuid,
                'user_id' => $dono,
                'name' => $nome,
                'status' => $arquivado ? ProjectStatus::Archived : ProjectStatus::Active,
                'created_at' => $criadoEm,
                'updated_at' => $criadoEm,
            ])->save();
        }
    }
}
