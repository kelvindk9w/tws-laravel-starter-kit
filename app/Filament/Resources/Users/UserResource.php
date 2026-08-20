<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Usuários (super admin — Fase 6): listar, ver e bloquear/desbloquear.
 *
 * Sem criar/editar/excluir pela UI: contas nascem pelo registro público e a
 * flag is_admin só muda via comando `user:make-admin` (trilha de auditoria).
 * Rotas e buscas usam o UUID — o id interno nunca é exposto (ADR-010).
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    public static function getNavigationLabel(): string
    {
        return __('admin.users.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural');
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
                // `name` é criptografado em repouso (checklist 12): exibido,
                // mas NÃO pesquisável/ordenável (a coluna é o ciphertext).
                TextColumn::make('name')
                    ->label(__('panel.common.name')),
                TextColumn::make('email')
                    ->label(__('auth.ui.email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('panel.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => __('admin.users.active'),
                        UserStatus::Blocked => __('admin.users.blocked'),
                        UserStatus::Pending => __('admin.users.pending'),
                    })
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Blocked => 'danger',
                        UserStatus::Pending => 'warning',
                    }),
                IconColumn::make('is_admin')
                    ->label(__('admin.users.admin'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.users.created_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.common.status'))
                    ->options([
                        UserStatus::Active->value => __('admin.users.active'),
                        UserStatus::Blocked->value => __('admin.users.blocked'),
                        UserStatus::Pending->value => __('admin.users.pending'),
                    ]),
                TernaryFilter::make('is_admin')
                    ->label(__('admin.users.admin')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('block')
                    ->label(__('admin.users.block'))
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Active)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.users.block_heading'))
                    ->modalDescription(fn (User $record): string => __('admin.users.block_warning', ['email' => $record->email]))
                    ->action(function (User $record): void {
                        $record->forceFill(['status' => UserStatus::Blocked])->save();

                        Notification::make()->success()->title(__('admin.users.blocked_success'))->send();
                    }),
                Action::make('unblock')
                    ->label(__('admin.users.unblock'))
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Blocked)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.users.unblock_heading'))
                    ->action(function (User $record): void {
                        $record->forceFill(['status' => UserStatus::Active])->save();

                        Notification::make()->success()->title(__('admin.users.unblocked_success'))->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('codigo_publico')->label(__('admin.users.code')),
                TextEntry::make('name')->label(__('panel.common.name')),
                TextEntry::make('email')->label(__('auth.ui.email')),
                TextEntry::make('status')
                    ->label(__('panel.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => __('admin.users.active'),
                        UserStatus::Blocked => __('admin.users.blocked'),
                        UserStatus::Pending => __('admin.users.pending'),
                    }),
                IconEntry::make('is_admin')->label(__('admin.users.admin'))->boolean(),
                IconEntry::make('transaction_password_set_at')
                    ->label(__('admin.users.transaction_password'))
                    ->boolean(fn ($state): bool => $state !== null),
                TextEntry::make('created_at')
                    ->label(__('admin.users.created_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
