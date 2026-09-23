<?php

declare(strict_types=1);

use App\Core\Auth\Support\DemoAccountTrigger;
use Illuminate\Database\Migrations\Migration;

/**
 * Fecha o TRUNCATE na blindagem das contas demo.
 *
 * O gatilho da migration 2026_09_05_000002 é de LINHA (BEFORE UPDATE OR
 * DELETE ... FOR EACH ROW), e TRUNCATE não passa por linha nenhuma:
 * `TRUNCATE users` apagava as contas demo sem esbarrar nele. O PostgreSQL só
 * oferece gatilho de TRUNCATE no nível da sentença, e é esse o gatilho que
 * esta migration acrescenta (ver DemoAccountTrigger).
 *
 * `install()` é idempotente e reinstala as duas peças com os e-mails
 * vigentes — num banco novo ele já roda completo na migration anterior, e
 * aqui só repete; num banco existente, é o que acrescenta o gatilho de
 * TRUNCATE. Continua no-op fora do PostgreSQL e com o modo demo desligado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DemoAccountTrigger::install();
    }

    public function down(): void
    {
        DemoAccountTrigger::dropTruncateGuard();
    }
};
