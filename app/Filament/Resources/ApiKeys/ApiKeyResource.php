<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Chaves de API — visão GLOBAL de todos os tenants (super admin, Fase 6).
 *
 * Somente leitura + revogação administrativa (mesma operação do
 * ApiKeyService da Fase 4 — nada duplicado). A secreta NUNCA aparece aqui
 * (no banco só existe o hash — ADR-006). Sem criar/editar: chaves nascem
 * pelo painel do próprio usuário ou pela API v1.
 */
final class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    // Navegação do /admin: TODO resource tem ícone (crítica de design #6 —
    // metade da nav aparecia como bolinha sem ícone).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function getNavigationLabel(): string
    {
        return __('admin.api_keys.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.api_keys.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.api_keys.plural');
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
                TextColumn::make('public_key')
                    ->label(__('admin.api_keys.public_key'))
                    ->copyable()
                    ->searchable()
                    ->limit(24),
                TextColumn::make('owner.email')
                    ->label(__('admin.api_keys.owner'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('panel.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (ApiKeyStatus $state): string => match ($state) {
                        ApiKeyStatus::Active => __('admin.api_keys.status_active'),
                        ApiKeyStatus::Revoked => __('admin.api_keys.status_revoked'),
                        ApiKeyStatus::Expired => __('admin.api_keys.status_expired'),
                        ApiKeyStatus::ExpiredInactivity => __('admin.api_keys.status_expired_inactivity'),
                        ApiKeyStatus::Rotated => __('admin.api_keys.status_rotated'),
                    })
                    ->color(fn (ApiKeyStatus $state): string => match ($state) {
                        ApiKeyStatus::Active => 'success',
                        ApiKeyStatus::Revoked => 'danger',
                        ApiKeyStatus::Rotated => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('last_used_at')
                    ->label(__('admin.api_keys.last_used'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->placeholder(__('admin.api_keys.never'))
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('admin.api_keys.expires_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->placeholder(__('admin.api_keys.no_expiration')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.common.status'))
                    ->options([
                        ApiKeyStatus::Active->value => __('admin.api_keys.status_active'),
                        ApiKeyStatus::Revoked->value => __('admin.api_keys.status_revoked'),
                        ApiKeyStatus::Expired->value => __('admin.api_keys.status_expired'),
                        ApiKeyStatus::ExpiredInactivity->value => __('admin.api_keys.status_expired_inactivity'),
                        ApiKeyStatus::Rotated->value => __('admin.api_keys.status_rotated'),
                    ]),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('admin.api_keys.revoke'))
                    ->color('danger')
                    ->visible(fn (ApiKey $record): bool => $record->status === ApiKeyStatus::Active)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.api_keys.revoke_heading'))
                    ->modalDescription(fn (ApiKey $record): string => __('admin.api_keys.revoke_warning', [
                        'key' => $record->public_key,
                        'owner' => $record->owner->email,
                    ]))
                    ->action(function (ApiKey $record, ApiKeyService $apiKeys): void {
                        $apiKeys->revoke($record);

                        Notification::make()->success()->title(__('admin.api_keys.revoked'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiKeys::route('/'),
        ];
    }
}
