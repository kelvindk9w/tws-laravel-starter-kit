<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormSubmissions;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\SubmissionExcerpt;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use App\Filament\Support\AdminColumns;
use App\Filament\Support\BaseResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Submissões de formulário (super admin). Somente leitura: nascem dos dois
 * forms demo do /ui e do formulário de CONTATO real da landing (origem
 * `contact` — a única com remetente identificado).
 *
 * A LISTAGEM NÃO MOSTRA PAYLOAD. Até aqui a tentativa de ataque aparecia
 * literal — escapada, portanto inerte, mas inteira e pronta para copiar.
 * Inerte não é inofensivo: a lista virava um catálogo de ataques na tela de
 * quem tem acesso ao painel. Hoje a listagem mostra o SELO do tipo de
 * ataque (XSS, SQLi, honeypot…) e um trecho NEUTRALIZADO
 * (SubmissionExcerpt: sem tags, colapsado, ~60 caracteres) marcado como
 * "conteúdo neutralizado".
 *
 * O PAYLOAD ÍNTEGRO VIVE NA TELA DE DETALHE (ViewFormSubmission), dentro de
 * um bloco de evidência forense com aviso — escapado pelo Filament, nunca
 * `->html()`. Quem precisa do payload inteiro (para investigar) tem um
 * clique de distância e sabe que está olhando para evidência; quem só
 * tria a fila nunca esbarra nele.
 *
 * A GRAVAÇÃO CONTINUA CRUA no banco (auditoria — ADR-004/005): quem
 * neutraliza é a exibição, nunca o registro.
 *
 * - ORDEM: tentativas bloqueadas SEMPRE no topo (blocked_at não nulo
 *   primeiro), depois as mais recentes.
 * - Filtro por origem refletido na URL (?filters[origin][value]=classic).
 */
final class FormSubmissionResource extends BaseResource
{
    protected static ?string $model = FormSubmission::class;

    protected static string $translationKey = 'admin.submissions';

    protected static ?string $navigationGroupKey = 'admin.nav.group_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    /**
     * Ordem própria (bloqueadas no topo) — ver tableExtras().
     */
    protected static ?string $defaultSortColumn = null;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Rótulo traduzido do tipo de ataque. Tipo desconhecido (detector novo,
     * registro antigo) cai num rótulo genérico em vez de imprimir a chave
     * de tradução crua na tela.
     */
    public static function attackLabel(?string $type): string
    {
        $key = self::$translationKey.'.attack_'.(string) $type;

        return $type !== null && Lang::has($key) ? __($key) : __('admin.submissions.attack_unknown');
    }

    /**
     * Texto de exibição de um campo: bloqueada = NEUTRALIZADO; aceita =
     * texto normal truncado (o Filament escapa em ambos os casos).
     */
    public static function safeText(FormSubmission $record, string $field, int $limit = SubmissionExcerpt::MAX_LENGTH): string
    {
        $value = (string) ($record->{$field} ?? '');

        return $record->isBlocked()
            ? SubmissionExcerpt::neutralize($value, $limit)
            : Str::limit($value, $limit);
    }

    /**
     * Assunto: os formulários gravam uma CHAVE de assunto (traduzida em
     * contact.subjects.*). Chave conhecida = assunto de verdade, mesmo numa
     * submissão bloqueada — o ataque costuma vir na mensagem, não aqui.
     * Qualquer outra coisa nesse campo é conteúdo de terceiro e sai
     * neutralizada.
     */
    public static function subjectLabel(FormSubmission $record): string
    {
        $key = "contact.subjects.{$record->subject}";

        if (Lang::has($key)) {
            return __($key);
        }

        return self::safeText($record, 'subject', 30);
    }

    // -------------------------------------------------------------------
    // Colunas (compartilhadas entre a tabela e os cards)
    // -------------------------------------------------------------------

    private static function securityColumn(): TextColumn
    {
        return TextColumn::make('attack_type')
            ->label(__('admin.submissions.security'))
            ->badge()
            ->icon(fn (FormSubmission $record) => $record->isBlocked() ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedCheckCircle)
            ->getStateUsing(fn (FormSubmission $record): string => $record->isBlocked()
                ? __('admin.submissions.blocked_attack', ['type' => self::attackLabel($record->attack_type)])
                : __('admin.submissions.accepted'))
            ->color(fn (FormSubmission $record): string => $record->isBlocked() ? 'danger' : 'success');
    }

    private static function originColumn(): TextColumn
    {
        return TextColumn::make('origin')
            ->label(__('admin.submissions.origin'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => __("admin.submissions.origin_{$state}"))
            ->color(fn (string $state): string => match ($state) {
                FormSubmission::ORIGIN_CLASSIC => 'info',
                FormSubmission::ORIGIN_CONTACT => 'warning',
                default => 'primary',
            });
    }

    /**
     * A coluna que antes vazava o payload. Agora: trecho neutralizado e,
     * quando bloqueada, a legenda que diz exatamente o que está sendo lido.
     */
    private static function messageColumn(): TextColumn
    {
        return TextColumn::make('message')
            ->label(__('admin.submissions.message'))
            ->getStateUsing(fn (FormSubmission $record): string => self::safeText($record, 'message'))
            ->description(fn (FormSubmission $record): ?string => $record->isBlocked()
                ? __('admin.submissions.neutralized')
                : null)
            ->wrap();
    }

    private static function nicknameColumn(): TextColumn
    {
        return TextColumn::make('nickname')
            ->label(__('admin.submissions.nickname'))
            ->getStateUsing(fn (FormSubmission $record): string => self::safeText($record, 'nickname', 24))
            ->searchable();
    }

    /**
     * Colunas da listagem clássica.
     */
    public static function tableColumns(): array
    {
        return [
            self::securityColumn(),
            self::nicknameColumn(),
            // Só a origem `contact` tem remetente (forms demo são anônimos).
            TextColumn::make('sender_email')
                ->label(__('admin.submissions.sender_email'))
                ->searchable()
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('subject')
                ->label(__('admin.submissions.subject'))
                ->getStateUsing(fn (FormSubmission $record): string => self::subjectLabel($record))
                ->badge()
                ->color('gray'),
            self::messageColumn(),
            self::originColumn(),
            AdminColumns::dateTime('created_at', __('admin.submissions.received_at')),
        ];
    }

    /**
     * Modo cards: o que interessa numa triagem, em ordem de leitura —
     * o que aconteceu (selo), de quem veio, o que dizia (neutralizado) e
     * por onde entrou/quando.
     */
    public static function cardComponents(): array
    {
        return [
            Stack::make([
                Split::make([
                    self::securityColumn(),
                    AdminColumns::dateTime('created_at', __('admin.submissions.received_at'))
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->grow(false),
                ]),
                self::nicknameColumn()
                    ->weight(FontWeight::SemiBold)
                    ->size(TextSize::Large),
                self::messageColumn(),
                self::originColumn(),
            ])->space(2),
        ];
    }

    protected static function tableExtras(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                // Bloqueados no topo (é a fila que exige ação), depois os
                // mais recentes.
                ->orderByRaw('(blocked_at IS NOT NULL) DESC')
                ->orderByDesc('created_at'))
            ->filters([
                // SelectFilter (valores string): estado limpo na query string.
                SelectFilter::make('origin')
                    ->label(__('admin.submissions.origin'))
                    ->options([
                        FormSubmission::ORIGIN_CLASSIC => __('admin.submissions.origin_classic'),
                        FormSubmission::ORIGIN_LIVEWIRE => __('admin.submissions.origin_livewire'),
                        FormSubmission::ORIGIN_CONTACT => __('admin.submissions.origin_contact'),
                    ]),
                TernaryFilter::make('blocked')
                    ->label(__('admin.submissions.filter_blocked'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('blocked_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('blocked_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin.submissions.view_evidence')),
            ])
            ->toolbarActions([]);
    }

    /**
     * Tela de DETALHE: metadados + o payload íntegro, escapado, marcado
     * como evidência forense.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.submissions.metadata_section'))
                    ->description(__('admin.submissions.metadata_hint'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('attack_type')
                            ->label(__('admin.submissions.security'))
                            ->badge()
                            ->getStateUsing(fn (FormSubmission $record): string => $record->isBlocked()
                                ? __('admin.submissions.blocked_attack', ['type' => self::attackLabel($record->attack_type)])
                                : __('admin.submissions.accepted'))
                            ->color(fn (FormSubmission $record): string => $record->isBlocked() ? 'danger' : 'success'),
                        TextEntry::make('origin')
                            ->label(__('admin.submissions.origin'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("admin.submissions.origin_{$state}")),
                        TextEntry::make('sender_email')
                            ->label(__('admin.submissions.sender_email'))
                            ->placeholder(__('admin.submissions.no_sender')),
                        TextEntry::make('ip')
                            ->label(__('admin.submissions.ip'))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('admin.submissions.received_at'))
                            ->dateTime('d/m/Y H:i:s', platform()->displayTimezone),
                        TextEntry::make('blocked_at')
                            ->label(__('admin.submissions.blocked_at'))
                            ->dateTime('d/m/Y H:i:s', platform()->displayTimezone)
                            ->placeholder('—'),
                    ]),

                // Submissão legítima: a mensagem é só uma mensagem.
                Section::make(__('admin.submissions.content_section'))
                    ->columnSpanFull()
                    ->visible(fn (FormSubmission $record): bool => ! $record->isBlocked())
                    ->schema([
                        TextEntry::make('nickname')->label(__('admin.submissions.nickname')),
                        TextEntry::make('subject')
                            ->label(__('admin.submissions.subject'))
                            ->getStateUsing(fn (FormSubmission $record): string => self::subjectLabel($record)),
                        TextEntry::make('message')
                            ->label(__('admin.submissions.message'))
                            ->columnSpanFull(),
                    ]),

                // Submissão bloqueada: evidência, com o nome disso na tela.
                Section::make(__('admin.submissions.forensic_section'))
                    ->columnSpanFull()
                    ->visible(fn (FormSubmission $record): bool => $record->isBlocked())
                    ->schema([
                        Callout::make(__('admin.submissions.forensic_heading'))
                            ->description(__('admin.submissions.forensic_warning'))
                            ->danger(),
                        self::forensicEntry('nickname', __('admin.submissions.raw_nickname')),
                        self::forensicEntry('subject', __('admin.submissions.raw_subject')),
                        self::forensicEntry('message', __('admin.submissions.raw_message'))->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Bloco de código da evidência: monoespaçado, quebrando em qualquer
     * ponto (payload não tem espaços) e SEM `copyable()` — copiar payload
     * com um clique é exatamente o que não se quer facilitar.
     *
     * O Filament escapa o estado por padrão (nunca `->html()`): o conteúdo
     * é texto na página, jamais marcação. Estilo inline porque a CSP do
     * /admin permite style-src 'unsafe-inline' e isto não merece um
     * arquivo de CSS próprio.
     */
    private static function forensicEntry(string $field, string $label): TextEntry
    {
        return TextEntry::make($field)
            ->label($label)
            ->fontFamily(FontFamily::Mono)
            ->size(TextSize::Small)
            ->placeholder('—')
            ->alignStart()
            ->extraAttributes([
                // `white-space` fica no padrão de propósito: com `pre-wrap`, a
                // indentação do próprio template do Filament entraria no bloco
                // e o payload apareceria empurrado para o meio da caixa.
                'style' => 'display:block;width:100%;text-align:start;word-break:break-all;'
                    .'padding:0.75rem;border-radius:0.5rem;'
                    .'border:1px solid rgb(from currentColor r g b / 0.15);'
                    .'background:rgb(from currentColor r g b / 0.05);',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
            'view' => ViewFormSubmission::route('/{record}'),
        ];
    }
}
