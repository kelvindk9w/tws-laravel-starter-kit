<?php

declare(strict_types=1);

namespace App\Core\Uploads\Enums;

/**
 * Ciclo de vida do registro de upload.
 *
 * Stored: arquivo validado, persistido e disponível. Arquivos REJEITADOS
 * nunca chegam ao banco nem ao disco (política do ADR-010: suspeita = fora).
 */
enum UploadStatus: string
{
    case Stored = 'stored';
    case Deleted = 'deleted';
}
