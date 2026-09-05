<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Support\UserAdminGuard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

/**
 * Usuários (super admin — ADR-011): CRUD completo — listar, ver, criar,
 * editar, bloquear/desbloquear e excluir.
 *
 * Histórico: até a Fase 6 o resource era só leitura (contas nasciam pelo
 * registro público e a flag is_admin só mudava por comando). O operador do
 * painel precisava de shell no servidor para cadastrar alguém, o que não se
 * sustenta em um super admin — a UI passou a fazer o ciclo inteiro. O
 * comando `user:make-admin` CONTINUA existindo e é o caminho de resgate
 * quando não há nenhum admin (bootstrap e recuperação de acesso).
 *
 * Guardas de servidor (UserAdminGuard, não apenas botão escondido):
 * contas demo intocáveis, o admin não se exclui nem se bloqueia e o último
 * admin ativo não perde a flag/acesso.
 *
 * `is_admin` e `status` NÃO são mass-assignable (ADR-011): as páginas de
 * criação/edição gravam por forceFill explícito.
 *
 * Rotas e buscas usam o UUID — o id interno nunca é exposto (ADR-010).
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

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

    /**
     * Formulário de criação/edição. `status` e `is_admin` são gravados por
     * forceFill nas páginas (nunca mass assignment — ADR-011).
     */
    public static function form(Schema $schema): Schema
    {
        $minimo = (int) config('auth.password_rules.min_length', 12);

        return $schema
            ->components([
                Section::make(__('admin.users.section_identity'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('panel.common.name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('email')
                            ->label(__('auth.ui.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make(__('admin.users.section_access'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        // Mesma política de senha do registro público
                        // (config auth.password_rules) — o admin não abre
                        // exceção para si mesmo.
                        TextInput::make('password')
                            ->label(__('admin.users.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::min($minimo)->letters()->mixedCase()->numbers())
                            ->same('password_confirmation')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? __('admin.users.password_hint_create', ['min' => $minimo])
                                : __('admin.users.password_hint_edit')),
                        TextInput::make('password_confirmation')
                            ->label(__('admin.users.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            // Confirmação nunca vai para o banco.
                            ->dehydrated(false),
                        Select::make('status')
                            ->label(__('admin.users.status'))
                            ->options([
                                UserStatus::Active->value => __('admin.users.active'),
                                UserStatus::Blocked->value => __('admin.users.blocked'),
                                UserStatus::Pending->value => __('admin.users.pending'),
                            ])
                            ->default(UserStatus::Active->value)
                            ->required()
                            ->selectablePlaceholder(false),
                        Toggle::make('is_admin')
                            ->label(__('admin.users.admin'))
                            ->helperText(__('admin.users.admin_hint')),
                    ]),
            ]);
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
                // Só o "sim" é sinalizado: um ⊗ vermelho em cada linha comum
                // (a maioria) transforma o estado normal em alarme e é a
                // única cor saturada do painel (crítica de design).
                IconColumn::make('is_admin')
                    ->label(__('admin.users.admin'))
                    ->boolean()
                    ->trueColor('gray')
                    ->falseIcon(null)
                    ->alignCenter(),
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
                EditAction::make()
                    ->visible(fn (User $record): bool => UserAdminGuard::editDenial($record) === null),
                Action::make('block')
                    ->label(__('admin.users.block'))
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Active)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.users.block_heading'))
                    ->modalDescription(fn (User $record): string => __('admin.users.block_warning', ['email' => $record->email]))
                    ->action(function (User $record): void {
                        // Guardas de servidor: conta demo, a própria conta e
                        // o último admin ativo não podem ser bloqueados.
                        if ($motivo = UserAdminGuard::blockDenial($record, auth()->user())) {
                            Notification::make()->danger()->title($motivo)->send();

                            return;
                        }

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
                        if ($record->isDemo()) {
                            Notification::make()->danger()->title(__('admin.users.demo_protected'))->send();

                            return;
                        }

                        $record->forceFill(['status' => UserStatus::Active])->save();

                        Notification::make()->success()->title(__('admin.users.unblocked_success'))->send();
                    }),
                DeleteAction::make()
                    ->label(__('admin.users.delete'))
                    ->modalHeading(__('admin.users.delete_heading'))
                    ->modalDescription(fn (User $record): string => __('admin.users.delete_warning', ['email' => $record->email]))
                    ->successNotificationTitle(__('admin.users.deleted_success'))
                    // Some quando proibido E é recusada no servidor (before).
                    ->visible(fn (User $record): bool => UserAdminGuard::deleteDenial($record, auth()->user()) === null)
                    ->before(function (User $record, DeleteAction $action): void {
                        if ($motivo = UserAdminGuard::deleteDenial($record, auth()->user())) {
                            Notification::make()->danger()->title(__('admin.users.action_denied'))->body($motivo)->send();

                            $action->cancel();
                        }
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
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
