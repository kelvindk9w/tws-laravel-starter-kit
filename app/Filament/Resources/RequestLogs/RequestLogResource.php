<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestLogs;

use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Filament\Resources\RequestLogs\Pages\ListRequestLogs;
use App\Filament\Resources\RequestLogs\Pages\ViewRequestLog;
use App\Filament\Support\AdminColumns;
use App\Filament\Support\BaseResource;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Request Logs — consulta de auditoria (super admin, Fase 6; ADR-004/010).
 *
 * ESTRITAMENTE read-only: a tabela é append-only por lei (o model bloqueia
 * update/delete fora do ciclo de vida). Filtros: status, tenant, endpoint e
 * período. Logs ÓRFÃOS (tenant_uuid null) são destacados em vermelho — log
 * sem tenant = possível ataque/tentativa de burla; log preso em INICIADA =
 * requisição que não chegou ao fim (incidente a investigar).
 */
final class RequestLogResource extends BaseResource
{
    protected static ?string $model = RequestLog::class;

    protected static string $translationKey = 'admin.request_logs';

    protected static ?string $navigationGroupKey = 'admin.nav.group_security';

    // Navegação do /admin: TODO resource tem ícone (crítica de design #6 —
    // metade da nav aparecia como bolinha sem ícone).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Label traduzido de um status do ciclo de vida (ADR-004).
     */
    public static function statusLabel(RequestLogStatus $status): string
    {
        return __('admin.request_logs.status_'.$status->value);
    }

    public static function statusColor(RequestLogStatus $status): string
    {
        return match ($status) {
            RequestLogStatus::Concluida => 'success',
            RequestLogStatus::Erro => 'danger',
            // INICIADA persistente = incidente; BLOQUEADA = ataque rejeitado.
            RequestLogStatus::Iniciada, RequestLogStatus::Bloqueada => 'warning',
        };
    }

    public static function tableColumns(): array
    {
        return [
            AdminColumns::dateTime('created_at', __('panel.common.created_at'), 'd/m/Y H:i:s'),
            self::statusColumn(),
            self::tenantColumn(),
            self::methodColumn(),
            self::endpointColumn(),
            TextColumn::make('http_status_response')
                ->label(__('admin.request_logs.response_status'))
                ->placeholder('—'),
            TextColumn::make('duration_ms')
                ->label(__('admin.request_logs.duration'))
                ->suffix(' ms')
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('ip')
                ->label(__('admin.request_logs.ip'))
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Modo cards: o endpoint em destaque (é o que se lê primeiro numa
     * investigação), com status, tenant e quando aconteceu em volta.
     */
    public static function cardComponents(): array
    {
        return [
            Stack::make([
                Split::make([
                    self::methodColumn()->grow(false),
                    self::endpointColumn()
                        ->weight(FontWeight::SemiBold)
                        ->limit(80),
                ]),
                Split::make([
                    self::statusColumn(),
                    TextColumn::make('http_status_response')
                        ->label(__('admin.request_logs.response_status'))
                        ->badge()
                        ->color('gray')
                        ->placeholder('—')
                        ->grow(false),
                    TextColumn::make('duration_ms')
                        ->label(__('admin.request_logs.duration'))
                        ->suffix(' ms')
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->placeholder('—')
                        ->grow(false),
                ]),
                self::tenantColumn(),
                AdminColumns::dateTime('created_at', __('panel.common.created_at'), 'd/m/Y H:i:s')
                    ->size(TextSize::Small)
                    ->color('gray'),
            ])->space(2),
        ];
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('panel.common.status'))
            ->badge()
            ->formatStateUsing(fn (RequestLogStatus $state): string => self::statusLabel($state))
            ->color(fn (RequestLogStatus $state): string => self::statusColor($state));
    }

    private static function tenantColumn(): TextColumn
    {
        return TextColumn::make('tenant_uuid')
            ->label(__('admin.request_logs.tenant'))
            // ÓRFÃO destacado: log sem tenant = sinal de ataque (ADR-010).
            // A linha inteira também fica vermelha (recordClasses).
            ->placeholder(__('admin.request_logs.orphan'))
            ->badge()
            ->color(fn (?string $state): string => $state === null ? 'danger' : 'gray')
            ->limit(14);
    }

    private static function methodColumn(): TextColumn
    {
        return TextColumn::make('method')
            ->badge()
            ->color('gray');
    }

    private static function endpointColumn(): TextColumn
    {
        return TextColumn::make('endpoint')
            ->label(__('admin.request_logs.endpoint'))
            ->limit(48)
            ->searchable();
    }

    protected static function tableExtras(Table $table): Table
    {
        return $table
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.request_logs.filter_status'))
                    ->options(collect(RequestLogStatus::cases())
                        ->mapWithKeys(fn (RequestLogStatus $s): array => [$s->value => self::statusLabel($s)])
                        ->all()),
                Filter::make('tenant_uuid')
                    ->label(__('admin.request_logs.filter_tenant'))
                    ->schema([
                        TextInput::make('tenant')->label(__('admin.request_logs.filter_tenant')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['tenant'] ?? null),
                        fn (Builder $q): Builder => $q->where('tenant_uuid', (string) $data['tenant']),
                    )),
                Filter::make('endpoint')
                    ->label(__('admin.request_logs.filter_endpoint'))
                    ->schema([
                        TextInput::make('contains')->label(__('admin.request_logs.filter_endpoint')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['contains'] ?? null),
                        fn (Builder $q): Builder => $q->where('endpoint', 'like', '%'.str_replace(['%', '_'], '', (string) $data['contains']).'%'),
                    )),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.request_logs.filter_from')),
                        DatePicker::make('until')->label(__('admin.request_logs.filter_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '>=', (string) $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '<=', (string) $data['until']))),
                TernaryFilter::make('orphans')
                    ->label(__('admin.request_logs.only_orphans'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('tenant_uuid'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('tenant_uuid'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            // Linha inteira destacada para órfãos e incidentes (INICIADA/ERRO).
            ->recordClasses(fn (RequestLog $record): ?string => match (true) {
                $record->tenant_uuid === null => 'bg-red-50 dark:bg-red-950/30',
                $record->status === RequestLogStatus::Iniciada => 'bg-amber-50 dark:bg-amber-950/30',
                default => null,
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('correlation_id')->label('Correlation ID')->copyable(),
                TextEntry::make('status')
                    ->label(__('panel.common.status'))
                    ->badge()
                    ->formatStateUsing(fn (RequestLogStatus $state): string => self::statusLabel($state))
                    ->color(fn (RequestLogStatus $state): string => self::statusColor($state)),
                TextEntry::make('tenant_uuid')
                    ->label(__('admin.request_logs.tenant'))
                    ->formatStateUsing(fn (?string $state): string => $state ?? __('admin.request_logs.orphan'))
                    ->helperText(__('admin.request_logs.orphan_hint')),
                TextEntry::make('method'),
                TextEntry::make('endpoint')->label(__('admin.request_logs.endpoint')),
                TextEntry::make('http_status_response')->label(__('admin.request_logs.response_status'))->placeholder('—'),
                TextEntry::make('duration_ms')->label(__('admin.request_logs.duration'))->suffix(' ms')->placeholder('—'),
                TextEntry::make('ip')->label(__('admin.request_logs.ip'))->placeholder('—'),
                TextEntry::make('user_agent')->label('User-Agent')->placeholder('—'),
                TextEntry::make('error_message')->label(__('admin.request_logs.error'))->placeholder('—'),
                TextEntry::make('created_at')
                    ->label(__('panel.common.created_at'))
                    ->dateTime('d/m/Y H:i:s', platform()->displayTimezone),
                KeyValueEntry::make('payload')
                    ->label(__('admin.request_logs.payload'))
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequestLogs::route('/'),
            'view' => ViewRequestLog::route('/{record}'),
        ];
    }
}
