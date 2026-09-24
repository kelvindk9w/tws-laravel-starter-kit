<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Configuração do Pest 4.
// Feature: roda com a aplicação Laravel completa + banco de teste — SQLite em
// memória no phpunit.xml (padrão local) ou PostgreSQL no phpunit.pgsql.xml (CI).

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Testes da DEMONSTRAÇÃO do kit (App\Demo): mesma base dos de Feature. Ficam
// separados para sair junto com a demo, e todos no grupo `demo`.
//
// Caso de teste do PRODUTO que exercita uma peça da demo (conta demo, tela de
// produtos/submissões, landing, vitrine, contato) também leva `->group('demo')`.
// A suíte do produto sem a demo é:
//     pest --testsuite=Unit,Feature --exclude-group=demo
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('demo')
    ->in('Demo');

// Helpers compartilhados da suíte de API Keys/Tenancy.
require_once __DIR__.'/Feature/ApiKeys/Helpers.php';

// Fixtures programáticas de arquivos da suíte de Uploads.
require_once __DIR__.'/Fixtures/uploads.php';

// Chamadas reais ao endpoint de atualização do Livewire (barreiras de acesso
// que só existem na rota de verdade — ver o próprio arquivo).
require_once __DIR__.'/Feature/Support/LivewireEndpoint.php';

// Unit: sobe a aplicação (sem banco) para helpers que dependem do container
// (ex.: platform()). Testes puramente isolados continuam funcionando.
pest()->extend(TestCase::class)
    ->in('Unit');
