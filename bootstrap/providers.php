<?php

use App\Core\Settings\Providers\SettingsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    AdminPanelProvider::class,
];
