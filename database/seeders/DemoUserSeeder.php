<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuário de demonstração (fricção zero em desenvolvimento — padrão
 * demo.filamentphp.com): credenciais conhecidas pré-preenchidas na tela de
 * login quando config('ui.demo_login.enabled') — padrão APENAS em APP_ENV=local.
 *
 * NUNCA rodar/habilitar em produção (ver .env.prod.example).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('ui.demo_login.email')],
            [
                'name' => 'Usuário Demo',
                'password' => Hash::make((string) config('ui.demo_login.password')),
                'email_verified_at' => now(),
            ],
        );
    }
}
