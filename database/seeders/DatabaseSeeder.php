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
        // Usuário demo + super admin demo (credenciais conhecidas) e vitrine
        // de produtos apenas quando habilitado — padrão: APP_ENV=local
        // (config/ui.php). Nunca em produção.
        if (config('ui.demo_login.enabled')) {
            $this->call(DemoUserSeeder::class);
            $this->call(DemoAdminSeeder::class);
            // Massa de usuários: paginação e filtros do /admin nascem com
            // conteúdo em qualquer instalação (idempotente — semente fixa).
            $this->call(UserSeeder::class);
            $this->call(ProductSeeder::class);
            $this->call(FormSubmissionSeeder::class);
            // ~30 dias de request_logs realistas: o gráfico do dashboard do
            // /admin nasce com conteúdo em qualquer instalação (append-only
            // e idempotente — ver RequestLogSeeder).
            $this->call(RequestLogSeeder::class);
        }
    }
}
