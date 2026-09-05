<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Auth\Support\DemoAccountGuard;
use App\Core\Auth\Support\DemoAccountTrigger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuário de demonstração (fricção zero em desenvolvimento — padrão
 * demo.filamentphp.com): credenciais conhecidas pré-preenchidas na tela de
 * login quando config('ui.demo_login.enabled') — padrão APENAS em APP_ENV=local.
 *
 * NOME: "Cliente Demo", não "Usuário Demo". Quem abre o painel é o CLIENTE
 * do produto, e "Usuário Demo" ao lado de "Admin Demo" fazia parecer que as
 * duas contas eram do mesmo lado do balcão. O nome diz de qual lado se está.
 *
 * A semeadura roda dentro de DemoAccountGuard::withoutProtection(): é o
 * único lugar legítimo que precisa gravar senha e e-mail de conta demo — a
 * blindagem recusaria (model + gatilho do PostgreSQL). No mesmo passo o
 * gatilho é reinstalado, para o banco acompanhar o e-mail que está no .env.
 *
 * NUNCA rodar/habilitar em produção (ver .env.prod.example).
 */
class DemoUserSeeder extends Seeder
{
    public const NOME = 'Cliente Demo';

    public function run(): void
    {
        DemoAccountGuard::withoutProtection(function (): void {
            User::query()->updateOrCreate(
                ['email' => config('ui.demo_login.email')],
                [
                    'name' => self::NOME,
                    'password' => Hash::make((string) config('ui.demo_login.password')),
                    'email_verified_at' => now(),
                ],
            );
        });

        DemoAccountTrigger::install();
    }
}
