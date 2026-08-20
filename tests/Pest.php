<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Configuração do Pest 4.
// Feature: roda com a aplicação Laravel completa + banco em memória (sqlite).

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit: sobe a aplicação (sem banco) para helpers que dependem do container
// (ex.: platform()). Testes puramente isolados continuam funcionando.
pest()->extend(TestCase::class)
    ->in('Unit');
