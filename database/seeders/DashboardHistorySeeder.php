<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Histórico de demonstração dos DASHBOARDS do /admin.
 *
 * POR QUE ISTO EXISTE. O kit já semeava usuários (90 dias) e request_logs (30
 * dias), mas `projects`, `api_keys` e `uploads` nasciam VAZIOS e as submissões
 * cabiam todas nas últimas 40 horas. Com isso, um /admin recém-instalado abria
 * três dashboards com metade dos cards em zero e dois gráficos com o estado
 * vazio — exatamente a impressão contrária à que o painel deveria dar.
 *
 * O que este agregador chama semeia PROFUNDIDADE NO TEMPO (até ~200 dias) para
 * que as três janelas do seletor (7/30/90 dias) tenham dados E período
 * anterior — sem período anterior, todo card diria "sem base de comparação" e
 * o Δ% ficaria decorativo.
 *
 * TODOS os seeders chamados aqui são IDEMPOTENTES por identificador
 * determinístico (UUID v5 com semente fixa): rodar `db:seed` dez vezes produz
 * exatamente as mesmas linhas. Nenhum deles altera seeder existente.
 */
final class DashboardHistorySeeder extends Seeder
{
    /**
     * Janela mais longa semeada, em dias. Cobre o período de 90 dias E o seu
     * período anterior (90 + 90), com folga.
     */
    public const DIAS = 200;

    /**
     * Semente compartilhada: é ela que torna todo o conjunto idempotente.
     */
    public const SEMENTE = 20260905;

    /**
     * Namespace dos identificadores determinísticos (UUID v5).
     */
    public const NAMESPACE_UUID = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    public function run(): void
    {
        $this->call(ProjectSeeder::class);
        $this->call(ApiKeySeeder::class);
        $this->call(UploadSeeder::class);
        $this->call(SubmissionHistorySeeder::class);
        $this->call(ProductHistorySeeder::class);
        $this->call(RequestLogHistorySeeder::class);
    }
}
