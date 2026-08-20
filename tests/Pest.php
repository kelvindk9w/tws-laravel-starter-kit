<?php

declare(strict_types=1);

// Configuração do Pest 4.
// Feature: roda com a aplicação Laravel completa + banco em memória (sqlite).

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
