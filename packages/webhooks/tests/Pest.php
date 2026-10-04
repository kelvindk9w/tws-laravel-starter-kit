<?php

declare(strict_types=1);

use Twstec\Kit\Webhooks\Tests\TestCase;

// O exemplo de verificação da documentação — o MESMO código que o receptor de
// teste usa (e que docs/webhooks.md mostra).
require_once dirname(__DIR__).'/examples/verify-signature.php';

// Todos os testes do pacote sobem a aplicação limpa do Testbench com os
// providers do pacote, do accounts, do auth e do foundation — nada do starter.
pest()->extend(TestCase::class)->in('Feature', 'Protections', 'Architecture');
