<?php

declare(strict_types=1);

use App\Core\Support\Platform;

// =============================================================================
// Helpers globais do starter kit.
// Carregado via composer.json → autoload.files.
// =============================================================================

if (! function_exists('platform')) {
    /**
     * Acesso global tipado à configuração da plataforma (ADR-007).
     *
     * Ex.: platform()->name, platform()->officialUrl, platform()->supportEmail
     */
    function platform(): Platform
    {
        return app(Platform::class);
    }
}
