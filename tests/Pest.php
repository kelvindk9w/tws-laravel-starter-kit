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
