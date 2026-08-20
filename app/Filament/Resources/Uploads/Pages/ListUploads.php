<?php

declare(strict_types=1);

namespace App\Filament\Resources\Uploads\Pages;

use App\Filament\Resources\Uploads\UploadResource;
use Filament\Resources\Pages\ListRecords;

final class ListUploads extends ListRecords
{
    protected static string $resource = UploadResource::class;
}
