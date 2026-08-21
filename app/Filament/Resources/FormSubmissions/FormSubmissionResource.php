<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormSubmissions;

use App\Core\Showcase\Models\FormSubmission;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Submissões dos formulários demo do /ui (super admin — vitrine de
 * segurança). Somente leitura: nascem dos dois forms do showcase.
 *
 * - ORDEM: tentativas bloqueadas SEMPRE no topo (blocked_at não nulo
 *   primeiro), depois as mais recentes — badge vermelho "ataque bloqueado".
 * - XSS fica armazenado INERTE: as colunas de texto do Filament escapam por
 *   padrão (nenhum ->html() aqui) — o payload aparece literal e nunca
 *   executa (coberto por teste).
 * - Filtro por origem refletido na URL (?filters[origin][value]=classic) —
 *   ver o #[Url] na ListFormSubmissions.
 */
final class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    public static function getNavigationLabel(): string
    {
        return __('admin.submissions.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.submissions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.submissions.plural');
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
            ->modifyQueryUsing(fn ($query) => $query
                // Bloqueados no topo (vitrine), depois os mais recentes.
                ->orderByRaw('(blocked_at IS NOT NULL) DESC')
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('nickname')
                    ->label(__('admin.submissions.nickname'))
                    ->searchable()
                    ->limit(24),
                TextColumn::make('subject')
                    ->label(__('admin.submissions.subject'))
                    ->formatStateUsing(fn (string $state): string => __("contact.subjects.{$state}"))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('message')
                    ->label(__('admin.submissions.message'))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('origin')
                    ->label(__('admin.submissions.origin'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("admin.submissions.origin_{$state}"))
                    ->color(fn (string $state): string => $state === FormSubmission::ORIGIN_CLASSIC ? 'info' : 'primary'),
                // Destaque da vitrine: badge vermelho para tentativa bloqueada.
                TextColumn::make('attack_type')
                    ->label(__('admin.submissions.security'))
                    ->badge()
                    ->getStateUsing(fn (FormSubmission $record): string => $record->isBlocked()
                        ? __('admin.submissions.blocked_attack', ['type' => $record->attack_type])
                        : __('admin.submissions.accepted'))
                    ->color(fn (FormSubmission $record): string => $record->isBlocked() ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label(__('admin.submissions.received_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->sortable(),
            ])
            ->filters([
                // SelectFilter (valores string): estado limpo na query string.
                SelectFilter::make('origin')
                    ->label(__('admin.submissions.origin'))
                    ->options([
                        FormSubmission::ORIGIN_CLASSIC => __('admin.submissions.origin_classic'),
                        FormSubmission::ORIGIN_LIVEWIRE => __('admin.submissions.origin_livewire'),
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
        ];
    }
}
