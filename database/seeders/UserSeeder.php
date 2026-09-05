<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Massa realista de usuários para o /admin (paginação e filtros visíveis em
 * QUALQUER instalação — antes o banco nascia com 2 contas e a paginação era
 * decorativa).
 *
 * Características:
 * - Faker pt_BR (nomes e e-mails plausíveis para o público do kit);
 * - variedade proposital: uma parte BLOQUEADA e uma parte com e-mail NÃO
 *   verificado, para que os filtros de status do admin tenham o que filtrar;
 * - `created_at` espalhado nos últimos 90 dias, para que ordenação por data
 *   e o gráfico do dashboard não saiam achatados;
 * - IDEMPOTENTE: o Faker roda com semente fixa, então os mesmos 40 e-mails
 *   saem em toda execução e o updateOrCreate não duplica nada;
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
        $faker = FakerFactory::create('pt_BR');
        $faker->seed(self::SEMENTE);

        // Senha única compartilhada: hash caro (Argon2id) uma vez só.
        $hash = Hash::make('Seed-usuario-2026');

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

            // 1 em cada 8 bloqueado; 1 em cada 6 com e-mail não verificado.
            $criadoEm = now()->subDays($faker->numberBetween(0, 90))
                ->setTime($faker->numberBetween(7, 22), $faker->numberBetween(0, 59));

            // Nunca no futuro: o dia 0 sorteado com hora 22h passaria de agora.
            if ($criadoEm->isFuture()) {
                $criadoEm = now();
            }

            $linhas[$email] = [
                'name' => $faker->name(),
                'status' => $indice % 8 === 3 ? UserStatus::Blocked : UserStatus::Active,
                'email_verified_at' => $indice % 6 !== 1 ? $criadoEm : null,
                'locale' => $faker->randomElement(platform()->availableLocales),
                'created_at' => $criadoEm,
            ];
        }

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
}
