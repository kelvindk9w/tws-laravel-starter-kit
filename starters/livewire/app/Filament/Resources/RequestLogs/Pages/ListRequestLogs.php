<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestLogs\Pages;

use App\Filament\Resources\RequestLogs\RequestLogResource;
use App\Filament\Support\BaseListRecords;

final class ListRequestLogs extends BaseListRecords
{
    protected static string $resource = RequestLogResource::class;
}
