<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Catalog\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Espalha os produtos da vitrine no TEMPO.
 *
 * O ProductSeeder (que este arquivo NÃO altera) cria o catálogo inteiro no
 * mesmo instante — perfeitamente correto para a listagem, mas transforma o
 * card "Produtos" do dashboard num número sem história: tudo cadastrado hoje,
 * nada no período anterior, Δ% impossível.
 *
 * Aqui cada produto recebe uma data de cadastro DERIVADA DO PRÓPRIO uuid
 * (hash → dia), o que faz este seeder ser idempotente por construção: rodar
 * de novo recalcula exatamente a mesma data. Não cria nem apaga nada; só
 * datar o que o ProductSeeder criou.
 */
final class ProductHistorySeeder extends Seeder
{
    public const DIAS = DashboardHistorySeeder::DIAS;

    public function run(): void
    {
        Product::query()
            ->orderBy('id')
            ->get(['id', 'uuid', 'created_at'])
            ->each(function (Product $produto): void {
                $semente = crc32((string) $produto->uuid);

                $dias = $semente % self::DIAS;
                $hora = 8 + ($semente % 12);

                $criadoEm = now()->startOfDay()->subDays($dias)->setTime($hora, $semente % 60, 0);

                // Nunca no futuro: o dia 0 com hora 19h passaria de agora. A
                // correção recua UM DIA (e não "agora"), para a data continuar
                // sendo função pura do uuid — reexecutar no mesmo dia não pode
                // mover nada.
                if ($criadoEm->isFuture()) {
                    $criadoEm = $criadoEm->subDay();
                }

                // Nada a fazer se a data já é a calculada — a âncora é o
                // INÍCIO DO DIA de hoje, então reexecutar no mesmo dia não
                // move nada, e reexecutar semanas depois reaproxima a vitrine
                // do presente (o que é o certo para uma demo).
                if ($produto->created_at?->equalTo($criadoEm)) {
                    return;
                }

                Product::query()
                    ->whereKey($produto->getKey())
                    ->update(['created_at' => $criadoEm]);
            });
    }
}
