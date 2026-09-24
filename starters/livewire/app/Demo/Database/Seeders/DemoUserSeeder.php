<?php

namespace App\Demo\Database\Seeders;

use App\Demo\Accounts\DemoAccountGuard;
use App\Demo\Accounts\DemoAccountTrigger;
use App\Demo\Support\DemoSurface;
use App\Models\User;
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
 * NUNCA roda em produção, e isso não depende mais de ninguém lembrar de
 * desligar a flag: com APP_ENV=production o seeder LANÇA (DemoSurface). O
 * único jeito de semear a demo em produção é declarar
 * DEMO_ALLOW_IN_PRODUCTION=true — ver .env.prod.example e docs/demo.md.
 */
class DemoUserSeeder extends Seeder
{
    public const NOME = 'Cliente Demo';

    public function run(): void
    {
        // Fail-closed: dado FICTÍCIO nunca entra num banco de produção só
        // porque alguém rodou o seeder. Lança (não sai em silêncio) — ver
        // DemoSurface e DemoSurfaceInProductionException.
        DemoSurface::ensureSeedingAllowed(self::class);

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
