<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Filament\Support\BaseListRecords;

final class ListApiKeys extends BaseListRecords
{
    protected static string $resource = ApiKeyResource::class;
}
