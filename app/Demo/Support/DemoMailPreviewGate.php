<?php

declare(strict_types=1);

namespace App\Demo\Support;

use App\Core\Mail\Contracts\MailPreviewGate;

/**
 * Com a demonstração instalada, a galeria de e-mails (`/mail-preview`) segue o
 * modo demo: a flag DEMO_LOGIN_ENABLED somada ao fail-closed de produção e ao
 * opt-out DEMO_ALLOW_IN_PRODUCTION (DemoSurface::mailPreviewEnabled). É a
 * regra de sempre do kit; sem a demo, vale a regra padrão do produto
 * (App\Core\Mail\Support\ConfiguredMailPreviewGate).
 */
final class DemoMailPreviewGate implements MailPreviewGate
{
    public function allows(): bool
    {
        return DemoSurface::mailPreviewEnabled();
    }
}
