<?php

use App\Core\Settings\Providers\SettingsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    SettingsServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
