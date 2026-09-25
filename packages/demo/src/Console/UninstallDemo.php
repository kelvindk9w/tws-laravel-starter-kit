<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Twstec\Kit\Demo\Accounts\DemoAccountTrigger;

/**
 * Tira do BANCO o que a demonstração instalou nele, antes de tirar o pacote
 * (`composer remove --dev twstec/kit-demo`).
 *
 * POR QUE EXISTE: os gatilhos do PostgreSQL que blindam as contas demo ficam
 * no banco depois que o pacote sai — e quem os desligava na sessão, quando o
 * modo demo está desligado, era o próprio pacote (DemoAccountSession). Sem a
 * demo, um banco que já a rodou continuaria recusando excluir ou alterar
 * `demo@…` e `admin@…` no próprio banco, contra o que a aplicação diz (sem a
 * demo, nenhuma conta é protegida). Este comando remove os gatilhos; com
 * `--drop-tables`, também as tabelas da demo (produtos e submissões) e o
 * registro das migrations dela, para uma reinstalação futura rodá-las de novo.
 *
 * O que NÃO sai: a massa fictícia que os seeders da demo gravaram nas tabelas
 * do produto (pessoas, contas, chaves, uploads, histórico). Num banco de
 * desenvolvimento, o caminho limpo é `migrate:fresh` depois de tirar a demo.
 */
final class UninstallDemo extends Command
{
    /**
     * Migrations da demo que criam as tabelas dela (a ordem de remoção é a
     * inversa). As dos gatilhos não criam tabela: saem do registro junto.
     *
     * @var list<string>
     */
    public const TABLES = ['form_submissions', 'products'];

    protected $signature = 'demo:uninstall
        {--drop-tables : Também apaga as tabelas da demo (products, form_submissions) e o registro das migrations dela}
        {--force : Não pede confirmação (inclusive em produção)}';

    protected $description = 'Remove do banco os gatilhos das contas demo (e, com --drop-tables, as tabelas da demo), antes de `composer remove --dev twstec/kit-demo`';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remover do banco o que a demonstração instalou?', ! $this->laravel->isProduction())) {
            $this->line('Nada foi alterado.');

            return self::FAILURE;
        }

        DemoAccountTrigger::drop();
        $this->info('Gatilhos das contas demo removidos (no PostgreSQL; nos outros bancos, não existem).');

        if ($this->option('drop-tables')) {
            foreach (self::TABLES as $table) {
                Schema::dropIfExists($table);
            }

            $migrations = array_map(
                static fn (string $path): string => basename($path, '.php'),
                glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [],
            );

            if (Schema::hasTable('migrations')) {
                DB::table('migrations')->whereIn('migration', $migrations)->delete();
            }

            $this->info('Tabelas da demo ('.implode(', ', self::TABLES).') e registro das migrations dela removidos.');
        }

        $this->line('Agora: composer remove --dev twstec/kit-demo (e, num banco de desenvolvimento, migrate:fresh para tirar a massa fictícia).');

        return self::SUCCESS;
    }
}
