<?php

declare(strict_types=1);

use Twstec\Kit\Demo\Tests\TestCase;

// Todos os testes do pacote sobem a aplicação limpa do Testbench com a
// descoberta de pacotes, o painel mínimo (só o AdminPlugin) e a demonstração
// — nada do starter.
pest()->extend(TestCase::class)->in('Feature', 'Protections', 'Architecture');
