<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\BaseListRecords;
use Filament\Actions\CreateAction;

final class ListUsers extends BaseListRecords
{
    protected static string $resource = UserResource::class;

    protected function getResourceHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.users.create')),
        ];
    }
}
