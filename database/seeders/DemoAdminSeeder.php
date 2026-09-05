<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Auth\Support\DemoAccountGuard;
use App\Core\Auth\Support\DemoAccountTrigger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Super admin de demonstração (/admin): credenciais conhecidas pré-preenchidas
 * no login do Filament quando config('ui.demo_login.enabled') — padrão APENAS
 * em APP_ENV=local. is_admin é concedido via forceFill (a flag NUNCA é
 * mass-assignable — ADR-011).
 *
 * A semeadura roda dentro de DemoAccountGuard::withoutProtection(): é o
 * único lugar legítimo que grava senha e is_admin de conta demo — a
 * blindagem (model + gatilho do PostgreSQL) recusaria. O gatilho é
 * reinstalado no mesmo passo, para o banco acompanhar o e-mail do .env.
 *
 * NUNCA rodar/habilitar em produção (ver .env.prod.example).
 */
class DemoAdminSeeder extends Seeder
{
    public const NOME = 'Admin Demo';

    public function run(): void
    {
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
