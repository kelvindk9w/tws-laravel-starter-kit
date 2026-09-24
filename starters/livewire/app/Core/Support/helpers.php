<?php

declare(strict_types=1);

use App\Core\Support\Platform;

// =============================================================================
// Helpers globais da base do kit (módulo Support).
// Carregado via composer.json → autoload.files.
//
// Cada módulo leva os próprios helpers, no próprio diretório:
//   setting()                     → app/Core/Settings/helpers.php
//   tenant(), tenantKey()         → app/Core/Tenancy/helpers.php
//   form_error_display(), field_error() (interface) → app/Support/ui-helpers.php
// =============================================================================

if (! function_exists('platform')) {
    /**
     * Acesso global tipado à configuração da plataforma (nada hardcoded).
     *
     * Ex.: platform()->name, platform()->officialUrl, platform()->supportEmail
     */
    function platform(): Platform
    {
        return app(Platform::class);
    }
}
