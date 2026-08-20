<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Core\Tenancy\Enums\ProjectStatus;
use App\Core\Tenancy\Models\Project;
use App\Filament\Resources\Projects\Pages\ListProjects;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Projetos — visão global (super admin, Fase 6). Somente leitura: projetos
 * são gerenciados pelo próprio usuário no painel (ADR-005).
 */
final class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    public static function getNavigationLabel(): string
    {
        return __('admin.projects.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.projects.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.projects.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_management');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo_publico')
                    ->label(__('admin.users.code'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('panel.common.name'))
                    ->searchable(),
                TextColumn::make('owner.email')
                    ->label(__('admin.projects.owner'))
                    ->searchable(),
                TextColumn::make('api_keys_count')
                    ->label(__('admin.projects.linked_keys'))
                    ->counts('apiKeys')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('panel.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => match ($state) {
                        ProjectStatus::Active => __('admin.projects.status_active'),
                        ProjectStatus::Archived => __('admin.projects.status_archived'),
                    })
                    ->color(fn (ProjectStatus $state): string => $state === ProjectStatus::Active ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label(__('panel.common.created_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.common.status'))
                    ->options([
                        ProjectStatus::Active->value => __('admin.projects.status_active'),
                        ProjectStatus::Archived->value => __('admin.projects.status_archived'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
        ];
    }
}
