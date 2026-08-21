<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuário demo (credenciais conhecidas) apenas quando habilitado —
        // padrão: APP_ENV=local (config/ui.php). Nunca em produção.
        if (config('ui.demo_login.enabled')) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
