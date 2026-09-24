<?php

use App\Core\Audit\Providers\AuditServiceProvider;
use App\Core\Mail\Providers\MailServiceProvider;
use App\Core\Settings\Providers\SettingsServiceProvider;
use App\Core\Tenancy\Providers\TenancyServiceProvider;
use App\Demo\Providers\DemoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AuditServiceProvider::class,
    MailServiceProvider::class,
    SettingsServiceProvider::class,
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,

    // A demonstração do kit (landings, vitrine /ui, contato, catálogo de
    // exemplo, contas demo e seeders de dado fictício). Tirar esta linha
    // desliga a demo; o outro ponto de ligação é o previews.php dela no
    // autoload.files do composer.json (ver App\Demo\Providers\DemoServiceProvider).
    DemoServiceProvider::class,
];
