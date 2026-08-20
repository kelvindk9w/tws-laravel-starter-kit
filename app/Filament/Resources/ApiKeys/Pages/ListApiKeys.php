<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Resources\Pages\ListRecords;

final class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;
}
