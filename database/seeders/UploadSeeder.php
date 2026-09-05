<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Uploads\Enums\UploadStatus;
use App\Core\Uploads\Models\Upload;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * Histórico de uploads (Fase 5) espalhado no tempo.
 *
 * Sem ele, o gráfico "Entrada por dia" e os cards de volume da variante
 * "Conteúdo & Operação" nascem vazios: a tabela `uploads` só ganha linha
 * quando alguém sobe um arquivo de verdade, e uma instalação nova não tem
 * ninguém.
 *
 * O que é semeado é REGISTRO, não arquivo: nada é escrito em disco. O `path`
 * segue a convenção da Fase 5 (uuid + extensão derivada do MIME real, nunca o
 * nome original), o `sha256` é determinístico e o volume por dia cresce
 * levemente com o tempo, para a curva ter forma em vez de ruído.
 *
 * IDEMPOTENTE: uuid derivado do índice (UUID v5 com semente fixa).
 */
final class UploadSeeder extends Seeder
{
    /**
     * Tipos aceitos pela política de upload do kit, com a faixa de tamanho
     * plausível de cada um (bytes) e a extensão do path.
     *
     * @var list<array{mime: string, ext: string, min: int, max: int, nome: string}>
     */
    private const TIPOS = [
        ['mime' => 'image/jpeg', 'ext' => 'jpg', 'min' => 120_000, 'max' => 2_400_000, 'nome' => 'foto'],
        ['mime' => 'image/png', 'ext' => 'png', 'min' => 40_000, 'max' => 1_800_000, 'nome' => 'captura'],
        ['mime' => 'image/webp', 'ext' => 'webp', 'min' => 25_000, 'max' => 900_000, 'nome' => 'banner'],
        ['mime' => 'application/pdf', 'ext' => 'pdf', 'min' => 90_000, 'max' => 5_200_000, 'nome' => 'contrato'],
        ['mime' => 'text/csv', 'ext' => 'csv', 'min' => 2_000, 'max' => 480_000, 'nome' => 'relatorio'],
    ];

    /**
     * Quantos dias de histórico e quantos uploads por dia (faixa).
     */
    public const DIAS = DashboardHistorySeeder::DIAS;

    /**
     * Quantos arquivos entraram naquele dia — função pura da data (ver a
     * mesma decisão comentada no SubmissionHistorySeeder: quantidade de linhas
     * de seeder idempotente não pode depender do estado global do Faker).
     *
     * Fim de semana mais fraco e um crescimento leve rumo ao presente: é o que
     * dá FORMA à série (uma reta não conta história nenhuma).
     */
    private static function volumeDoDia(Carbon $data, int $dia): int
    {
        $semente = crc32($data->toDateString().'-upload');

        $base = $data->isWeekend() ? 1 : 2;
        $tendencia = (int) floor((self::DIAS - $dia) / 60);

        return $base + ($semente % (3 + $tendencia));
    }

    public function run(): void
    {
        $usuarios = User::query()->orderBy('id')->pluck('id')->all();

        if ($usuarios === []) {
            return;
        }

        $tenants = User::query()->orderBy('id')->pluck('uuid')->all();

        $faker = FakerFactory::create('pt_BR');
        $faker->seed(DashboardHistorySeeder::SEMENTE);

        $indice = 0;

        for ($dia = self::DIAS - 1; $dia >= 0; $dia--) {
            $data = now()->subDays($dia);

            // Fim de semana mais fraco e um crescimento leve rumo ao presente:
            // é o que dá FORMA à série (uma reta não conta história nenhuma).
            $volume = self::volumeDoDia($data, $dia);

            for ($i = 0; $i < $volume; $i++) {
                $tipo = $faker->randomElement(self::TIPOS);
                $uuid = (string) Uuid::uuid5(DashboardHistorySeeder::NAMESPACE_UUID, 'upload-seed-'.$indice);

                $tamanho = $faker->numberBetween($tipo['min'], $tipo['max']);
                $viaApi = $faker->boolean(35);
                $dono = $faker->randomElement($usuarios);
                $tenant = $faker->randomElement($tenants);
                $sufixo = $faker->numberBetween(1, 999);
                $hora = $faker->numberBetween(7, 22);
                $minuto = $faker->numberBetween(0, 59);

                $indice++;

                if (Upload::query()->where('uuid', $uuid)->exists()) {
                    continue;
                }

                $criadoEm = $data->copy()->setTime($hora, $minuto);

                // Nunca no futuro: o dia 0 sorteado com hora 22h passaria de
                // agora, e um upload "que ainda vai acontecer" quebraria a
                // leitura de qualquer janela.
                if ($criadoEm->isFuture()) {
                    $criadoEm = now();
                }

                (new Upload)->forceFill([
                    'uuid' => $uuid,
                    // Upload via API pertence ao TENANT; via web, ao usuário
                    // autenticado (a mesma convenção do ResolveTenant).
                    'tenant_uuid' => $viaApi ? $tenant : null,
                    'user_id' => $viaApi ? null : $dono,
                    'disk' => 'public',
                    // Path = uuid + extensão do MIME real (nunca o nome enviado).
                    'path' => 'uploads/'.$uuid.'.'.$tipo['ext'],
                    'original_name' => $tipo['nome'].'-'.$sufixo.'.'.$tipo['ext'],
                    'mime' => $tipo['mime'],
                    'size' => $tamanho,
                    'sha256' => hash('sha256', 'upload-seed-'.$uuid),
                    'status' => UploadStatus::Stored,
                    'created_at' => $criadoEm,
                    'updated_at' => $criadoEm,
                ])->save();
            }
        }
    }
}
