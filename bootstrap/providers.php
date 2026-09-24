<?php

use App\Core\Audit\Providers\AuditServiceProvider;
use App\Core\Mail\Providers\MailServiceProvider;
use App\Core\Settings\Providers\SettingsServiceProvider;
use App\Core\Tenancy\Providers\TenancyServiceProvider;
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
];
