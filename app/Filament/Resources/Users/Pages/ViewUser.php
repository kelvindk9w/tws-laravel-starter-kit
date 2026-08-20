<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;
}
