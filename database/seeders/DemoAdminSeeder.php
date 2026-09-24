<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Auth\Support\DemoAccountGuard;
use App\Core\Auth\Support\DemoAccountTrigger;
use App\Core\Support\DemoSurface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Super admin de demonstração (/admin): credenciais conhecidas pré-preenchidas
 * no login do Filament quando config('ui.demo_login.enabled') — padrão APENAS
 * em APP_ENV=local. is_admin é concedido via forceFill (a flag NUNCA é
 * mass-assignable).
 *
 * A semeadura roda dentro de DemoAccountGuard::withoutProtection(): é o
 * único lugar legítimo que grava senha e is_admin de conta demo — a
 * blindagem (model + gatilho do PostgreSQL) recusaria. O gatilho é
 * reinstalado no mesmo passo, para o banco acompanhar o e-mail do .env.
 *
 * NUNCA roda em produção, e isso não depende mais de ninguém lembrar de
 * desligar a flag: com APP_ENV=production o seeder LANÇA (DemoSurface). É o
 * seeder mais perigoso do kit — um super admin com senha publicada no
 * .env.example — e por isso a recusa é alta e explícita, não silenciosa.
 */
class DemoAdminSeeder extends Seeder
{
    public const NOME = 'Admin Demo';

    public function run(): void
    {
        // Fail-closed: dado FICTÍCIO nunca entra num banco de produção só
        // porque alguém rodou o seeder. Lança (não sai em silêncio) — ver
        // DemoSurface e DemoSurfaceInProductionException.
        DemoSurface::ensureSeedingAllowed(self::class);

        DemoAccountGuard::withoutProtection(function (): void {
            $user = User::query()->firstOrNew(['email' => config('ui.demo_admin.email')]);

            $user->forceFill([
                'name' => self::NOME,
                'password' => Hash::make((string) config('ui.demo_admin.password')),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_admin' => true,
            ])->save();
        });

        DemoAccountTrigger::install();
    }
}
