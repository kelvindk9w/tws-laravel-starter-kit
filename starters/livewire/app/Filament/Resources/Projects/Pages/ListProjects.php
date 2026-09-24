<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BaseListRecords;

final class ListProjects extends BaseListRecords
{
    protected static string $resource = ProjectResource::class;
}
