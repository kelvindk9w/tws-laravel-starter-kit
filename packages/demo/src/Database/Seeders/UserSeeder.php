<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Database\Seeders;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Demo\Support\DemoSurface;

/**
 * Massa realista de usuários para o /admin (paginação e filtros visíveis em
 * QUALQUER instalação — antes o banco nascia com 2 contas e a paginação era
 * decorativa).
 *
 * Características:
 * - Faker pt_BR (nomes e e-mails plausíveis para o público do kit);
 * - variedade proposital: uma parte BLOQUEADA, para que o filtro de status
 *   do admin tenha o que filtrar;
 * - todos com e-mail JÁ CONFIRMADO: a verificação de e-mail é exigida para
 *   operar o painel e a API, e massa semeada que nasce barrada só produziria
 *   chaves de API semeadas que não autenticam;
 * - `created_at` espalhado nos últimos 90 dias, para que ordenação por data
 *   e o gráfico do dashboard não saiam achatados;
 * - IDEMPOTENTE: o Faker roda com semente fixa, então os mesmos 40 e-mails
 *   saem em toda execução e o updateOrCreate não duplica nada — com a coleta
 *   de ciclos do PHP DESLIGADA enquanto a lista é montada (ver linhas());
 * - NÃO toca nas contas demo (e-mails vindos de config/ui.php) nem em
 *   qualquer conta pré-existente fora desta lista.
 */
class UserSeeder extends Seeder
{
    /**
     * Quantos usuários a instalação garante (o admin pagina de 10 em 10).
     */
    public const QUANTIDADE = 40;

    /**
     * Semente do Faker: o que torna o seeder idempotente.
     */
    private const SEMENTE = 20260904;

    public function run(): void
    {
        // Fail-closed: dado FICTÍCIO nunca entra num banco de produção só
        // porque alguém rodou o seeder. Lança (não sai em silêncio) — ver
        // DemoSurface e DemoSurfaceInProductionException.
        DemoSurface::ensureSeedingAllowed(self::class);

        // Senha única compartilhada: hash caro (Argon2id) uma vez só.
        $hash = Hash::make('Seed-usuario-2026');

        $linhas = self::linhas();

        foreach ($linhas as $email => $dados) {
            $user = User::query()->firstOrNew(['email' => $email]);

            $user->forceFill([
                'name' => $dados['name'],
                // Usuário já existente mantém a senha que tem.
                'password' => $user->exists ? $user->password : $hash,
                'status' => $dados['status'],
                'email_verified_at' => $dados['email_verified_at'],
                'locale' => $dados['locale'],
                'created_at' => $dados['created_at'],
                'updated_at' => $dados['created_at'],
            ])->save();
        }
    }

    /**
     * A lista COMPLETA dos usuários semeados, indexada por e-mail — sempre a
     * mesma (semente fixa do Faker).
     *
     * A coleta de ciclos do PHP fica DESLIGADA enquanto a lista é montada.
     * Motivo (a instabilidade do teste de idempotência, na rodada paralela):
     * o Faker sorteia pelo `mt_rand` GLOBAL do processo, e todo gerador do
     * Faker, ao ser destruído, chama `mt_srand()` SEM semente — re-sorteia o
     * gerador global (Faker\Generator::__destruct). Como o gerador e os
     * provedores dele se referenciam (um ciclo), um Faker descartado antes —
     * de uma rodada anterior deste seeder, de uma factory, de outro teste no
     * mesmo processo — só é destruído quando a coleta de ciclos roda, num
     * momento imprevisível. Caindo no meio deste laço, o resto da lista saía
     * aleatório: e-mails novos, gente nova na segunda rodada. Sem coleta de
     * ciclos no laço, nenhum destrutor alheio roda aqui dentro; a lista
     * depende só da semente. O estado anterior da coleta é devolvido.
     *
     * @return array<string, array{name: string, status: UserStatus, email_verified_at: Carbon, locale: string, created_at: Carbon}>
     */
    public static function linhas(): array
    {
        $coletaLigada = gc_enabled();
        gc_disable();

        try {
            $faker = FakerFactory::create('pt_BR');
            $faker->seed(self::SEMENTE);

            $reservados = array_filter([
                config('ui.demo_login.email'),
                config('ui.demo_admin.email'),
            ]);

            // Monta a lista COMPLETA antes de gravar, indexada por e-mail: a
            // chave do array é o que garante 40 registros distintos mesmo que
            // o Faker repita um e-mail (o count final não depende de sorte).
            $linhas = [];

            while (count($linhas) < self::QUANTIDADE) {
                $email = $faker->unique()->safeEmail();

                if (in_array($email, $reservados, true) || isset($linhas[$email])) {
                    continue;
                }

                $indice = count($linhas);

                // 1 em cada 8 bloqueado.
                $criadoEm = now()->subDays($faker->numberBetween(0, 90))
                    ->setTime($faker->numberBetween(7, 22), $faker->numberBetween(0, 59));

                // Nunca no futuro: o dia 0 sorteado com hora 22h passaria de agora.
                if ($criadoEm->isFuture()) {
                    $criadoEm = now();
                }

                $linhas[$email] = [
                    'name' => $faker->name(),
                    'status' => $indice % 8 === 3 ? UserStatus::Blocked : UserStatus::Active,
                    'email_verified_at' => $criadoEm,
                    'locale' => $faker->randomElement(platform()->availableLocales),
                    'created_at' => $criadoEm,
                ];
            }

            return $linhas;
        } finally {
            if ($coletaLigada) {
                gc_enable();
            }
        }
    }
}
