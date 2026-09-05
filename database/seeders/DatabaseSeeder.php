<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semeadura da instalação de demonstração.
 *
 * SEM WithoutModelEvents (e isso é decisão, não esquecimento): os models do
 * kit preenchem `uuid` (HasUuids/booted) e `codigo_publico` (HasPublicCode) no
 * evento `creating`. Silenciar os eventos durante o `db:seed` fazia o
 * RequestLogSeeder estourar "null value in column uuid" e faria o mesmo com
 * projetos, chaves e uploads. Se algum seeder futuro precisar de silêncio, o
 * lugar do `WithoutModelEvents` é ELE, não este agregador.
 */
class DatabaseSeeder extends Seeder
{
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
            // Profundidade no tempo para os DASHBOARDS do /admin (projetos,
            // chaves de API, uploads, submissões antigas, datas dos produtos e
            // o passado dos request logs). Sem isto, metade dos cards das três
            // variantes nasceria em zero numa instalação nova. Todos
            // idempotentes — ver DashboardHistorySeeder.
            $this->call(DashboardHistorySeeder::class);
        }
    }
}
