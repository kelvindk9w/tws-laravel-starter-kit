<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Content;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\SubmissionExcerpt;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Widgets\Support\BaseLatestRecordsWidget;
use App\Filament\Widgets\Support\Period;
use BackedEnum;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;

/**
 * Fila de entrada dos formulários, com as tentativas BLOQUEADAS no topo — a
 * mesma ordem da listagem do recurso, pela mesma razão: o que precisa de
 * atenção humana não pode depender de rolagem.
 *
 * Nenhum payload aparece aqui: o apelido passa pelo SubmissionExcerpt e o
 * conteúdo íntegro segue vivendo só na tela de evidência forense.
 */
final class SubmissionsInbox extends BaseLatestRecordsWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 6];

    /**
     * @return Builder<FormSubmission>
     */
    protected function latestQuery(Period $period): Builder
    {
        return FormSubmission::query()->orderByRaw('case when blocked_at is null then 1 else 0 end');
    }

    protected function latestHeading(): string
    {
        return __('admin.dashboards.content.inbox');
    }

    protected function latestIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedInboxArrowDown;
    }

    protected function latestUrl(): ?string
    {
        return FormSubmissionResource::getUrl();
    }

    /**
     * @return array<TextColumn>
     */
    protected function latestColumns(): array
    {
        return [
            TextColumn::make('nickname')
                ->label(__('admin.dashboards.content.inbox_from'))
                ->weight(FontWeight::SemiBold)
                ->formatStateUsing(fn (?string $state): string => SubmissionExcerpt::neutralize($state, 30)),

            TextColumn::make('subject')
                ->label(__('admin.dashboards.content.inbox_subject'))
                ->color('gray')
                ->formatStateUsing(fn (?string $state): string => self::subjectLabel($state)),

            TextColumn::make('blocked_at')
                ->label(__('admin.dashboards.content.inbox_state'))
                ->badge()
                ->formatStateUsing(fn (FormSubmission $record): string => $record->isBlocked()
                    ? FormSubmissionResource::attackLabel($record->attack_type)
                    : __('admin.dashboards.common.received'))
                ->color(fn (FormSubmission $record): string => $record->isBlocked() ? 'danger' : 'gray')
                ->default('-'),

            $this->whenColumn(),
        ];
    }

    /**
     * Assunto traduzido; assunto desconhecido cai no texto cru em vez de
     * imprimir a chave de tradução na tela.
     */
    private static function subjectLabel(?string $subject): string
    {
        if ($subject === null) {
            return '—';
        }

        $chave = 'contact.subjects.'.$subject;

        return Lang::has($chave) ? __($chave) : $subject;
    }
}
