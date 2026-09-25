<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Database\Seeders;

use Illuminate\Database\Seeder;
use Twstec\Kit\Demo\Support\DemoSurface;

/**
 * Semeadura da instalação de DEMONSTRAÇÃO — o agregador da demo, chamado pelo
 * DatabaseSeeder do produto (registrado com a tag `database.seeders` pelo
 * DemoServiceProvider).
 *
 * SEM WithoutModelEvents, pelo mesmo motivo do DatabaseSeeder: os models
 * preenchem `uuid` e `codigo_publico` no evento `creating`.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // FAIL-CLOSED: em APP_ENV=production nada de demonstração é semeado,
        // nem com DEMO_LOGIN_ENABLED=true. Aqui a recusa é um AVISO e não uma
        // exceção — este agregador roda dentro do que um script de deploy
        // executa (`php artisan migrate --seed --force`), e derrubar o deploy
        // por causa de dado de demonstração trocaria uma armadilha por outra.
        // Quem chama um seeder demo DIRETAMENTE (`db:seed
        // --class='Twstec\Kit\Demo\Database\Seeders\DemoAdminSeeder'`) recebe
        // exceção: ali a intenção foi declarada e o silêncio enganaria.
        if (! DemoSurface::allowed()) {
            $this->command?->warn(
                'APP_ENV=production: semeadura de DEMONSTRAÇÃO recusada (contas demo, '
                .'massa fictícia e histórico dos dashboards não foram criados). Isto é a '
                .'proteção do kit, não uma falha. Para uma demo pública hospedada, declare '
                .'DEMO_ALLOW_IN_PRODUCTION=true.'
            );

            return;
        }

        // Usuário demo + super admin demo (credenciais conhecidas) e vitrine
        // de produtos apenas quando habilitado — padrão: APP_ENV=local
        // (config/ui.php). Nunca em produção.
        if (DemoSurface::loginEnabled()) {
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
