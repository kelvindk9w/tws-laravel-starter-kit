<?php

use App\Core\Mail\Providers\MailServiceProvider;
use App\Core\Settings\Providers\SettingsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    MailServiceProvider::class,
    SettingsServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
