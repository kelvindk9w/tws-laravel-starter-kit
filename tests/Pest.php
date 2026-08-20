<?php

declare(strict_types=1);

// Configuração do Pest 4.
// Feature: roda com a aplicação Laravel completa + banco em memória (sqlite).

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Unit: sobe a aplicação (sem banco) para helpers que dependem do container
// (ex.: platform()). Testes puramente isolados continuam funcionando.
pest()->extend(Tests\TestCase::class)
    ->in('Unit');
