<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Showcase\Models\FormSubmission;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * PROFUNDIDADE NO TEMPO para as submissões de formulário.
 *
 * O FormSubmissionSeeder (que este arquivo NÃO altera) enche a listagem do
 * super admin com 40 itens, mas todos dentro das últimas 40 horas — ótimo
 * para a tela de triagem, inútil para um gráfico de 30 ou 90 dias. Aqui
 * entram as mensagens ANTIGAS, espalhadas até 200 dias atrás, com a mesma
 * mistura de origens e uma minoria de tentativas bloqueadas.
 *
 * O conteúdo é banal de propósito: nada aqui é payload de ataque. As
 * tentativas realmente maliciosas (com payload inerte) continuam sendo
 * responsabilidade do FormSubmissionSeeder, que já as trata como evidência.
 *
 * IDEMPOTENTE: uuid derivado do índice (UUID v5 com semente fixa).
 */
final class SubmissionHistorySeeder extends Seeder
{
    public const DIAS = DashboardHistorySeeder::DIAS;

    /** @var list<string> */
    private const ASSUNTOS = ['suggestion', 'complaint', 'other'];

    /** @var list<string> */
    private const ORIGENS = [
        FormSubmission::ORIGIN_CLASSIC,
        FormSubmission::ORIGIN_LIVEWIRE,
        FormSubmission::ORIGIN_CONTACT,
    ];

    /**
     * Quantas mensagens chegaram naquele dia.
     *
     * DERIVADO DA DATA, não sorteado no Faker: o número de LINHAS de um seeder
     * idempotente não pode depender do estado do gerador aleatório, que é
     * global ao processo (mt_srand) e pode ter sido mexido por qualquer coisa
     * que rodou antes — dentro da suíte, inclusive por outro teste. O Faker
     * continua cuidando do CONTEÚDO; a quantidade é função pura do dia.
     */
    private static function volumeDoDia(Carbon $data): int
    {
        $semente = crc32($data->toDateString().'-submissao');

        return $data->isWeekend() ? $semente % 2 : 1 + ($semente % 3);
    }

    public function run(): void
    {
        $faker = FakerFactory::create('pt_BR');
        $faker->seed(DashboardHistorySeeder::SEMENTE);

        $indice = 0;

        for ($dia = self::DIAS - 1; $dia >= 2; $dia--) {
            $data = now()->subDays($dia);

            $volume = self::volumeDoDia($data);

            for ($i = 0; $i < $volume; $i++) {
                $uuid = (string) Uuid::uuid5(DashboardHistorySeeder::NAMESPACE_UUID, 'submission-seed-'.$indice);

                $origem = $faker->randomElement(self::ORIGENS);
                $assunto = $faker->randomElement(self::ASSUNTOS);
                $apelido = $faker->firstName();
                $mensagem = $faker->realText(120);
                $bloqueada = $faker->boolean(8);
                $tipoAtaque = $faker->randomElement(['xss', 'sqli', 'honeypot']);
                $hora = $faker->numberBetween(8, 22);
                $minuto = $faker->numberBetween(0, 59);

                $indice++;

                if (FormSubmission::query()->where('uuid', $uuid)->exists()) {
                    continue;
                }

                $criadaEm = $data->copy()->setTime($hora, $minuto);

                if ($criadaEm->isFuture()) {
                    $criadaEm = now();
                }

                (new FormSubmission)->forceFill([
                    'uuid' => $uuid,
                    'nickname' => $apelido,
                    'sender_email' => $origem === FormSubmission::ORIGIN_CONTACT
                        ? mb_strtolower($apelido).'@exemplo.com'
                        : null,
                    'subject' => $assunto,
                    // Texto comum: histórico de volume, não vitrine de ataque.
                    'message' => $mensagem,
                    'origin' => $origem,
                    'ip' => $faker->ipv4(),
                    'blocked_at' => $bloqueada ? $criadaEm : null,
                    'attack_type' => $bloqueada ? $tipoAtaque : null,
                    'created_at' => $criadaEm,
                    'updated_at' => $criadaEm,
                ])->save();
            }
        }
    }
}
