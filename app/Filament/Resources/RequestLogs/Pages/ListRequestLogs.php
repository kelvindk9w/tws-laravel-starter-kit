<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestLogs\Pages;

use App\Filament\Resources\RequestLogs\RequestLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListRequestLogs extends ListRecords
{
    protected static string $resource = RequestLogResource::class;
}
