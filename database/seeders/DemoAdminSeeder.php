<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Super admin de demonstração (/admin): credenciais conhecidas pré-preenchidas
 * no login do Filament quando config('ui.demo_login.enabled') — padrão APENAS
 * em APP_ENV=local. is_admin é concedido via forceFill (a flag NUNCA é
 * mass-assignable — ADR-011).
 *
 * NUNCA rodar/habilitar em produção (ver .env.prod.example).
 */
class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => config('ui.demo_admin.email')]);

        $user->forceFill([
            'name' => 'Admin Demo',
            'password' => Hash::make((string) config('ui.demo_admin.password')),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_admin' => true,
        ])->save();
    }
}
