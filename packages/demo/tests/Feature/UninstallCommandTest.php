<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// `demo:uninstall` — o passo ANTES de `composer remove --dev twstec/kit-demo`
// num banco que já rodou a demo: os gatilhos das contas demo (PostgreSQL) saem
// do banco, e com --drop-tables as tabelas da demo e o registro das
// migrations dela. (O gatilho em si é provado contra PostgreSQL na suíte do
// starter; aqui, SQLite, o comando só não pode falhar por não haver gatilho.)
// =============================================================================

it('sem --drop-tables, mantém as tabelas da demo', function (): void {
    $this->artisan('demo:uninstall', ['--force' => true])
        ->expectsOutputToContain('Gatilhos das contas demo removidos')
        ->assertSuccessful();

    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('form_submissions'))->toBeTrue();
});

it('com --drop-tables, apaga as tabelas e o registro das migrations da demo — e só dela', function (): void {
    $antes = DB::table('migrations')->count();

    $this->artisan('demo:uninstall', ['--force' => true, '--drop-tables' => true])->assertSuccessful();

    expect(Schema::hasTable('products'))->toBeFalse()
        ->and(Schema::hasTable('form_submissions'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%products%')->exists())->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%protect_demo_users%')->exists())->toBeFalse()
        ->and(DB::table('migrations')->count())->toBe($antes - 7)
        ->and(Schema::hasTable('users'))->toBeTrue();
});

it('sem --force, pergunta — e recusar não mexe em nada', function (): void {
    $this->artisan('demo:uninstall', ['--drop-tables' => true])
        ->expectsConfirmation('Remover do banco o que a demonstração instalou?', 'no')
        ->assertFailed();

    expect(Schema::hasTable('products'))->toBeTrue();
});
