<?php

declare(strict_types=1);

namespace App\Filament\Resources\Uploads\Pages;

use App\Filament\Resources\Uploads\UploadResource;
use App\Filament\Support\BaseListRecords;

final class ListUploads extends BaseListRecords
{
    protected static string $resource = UploadResource::class;
}
