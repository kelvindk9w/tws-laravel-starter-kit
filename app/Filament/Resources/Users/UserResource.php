<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Core\Auth\PasswordPolicy;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Support\UserAdminGuard;
use App\Filament\Support\AdminColumns;
use App\Filament\Support\AvatarUpload;
use App\Filament\Support\BaseResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
final class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static string $translationKey = 'admin.users';

    protected static ?string $navigationGroupKey = 'admin.nav.group_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /**
     * Formulário de criação/edição. `status` e `is_admin` são gravados por
     * forceFill nas páginas (nunca mass assignment — ADR-011).
     */
    public static function form(Schema $schema): Schema
    {
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
                        // A foto passa pela função global de upload do kit
                        // (SecureUploadService) e é servida por URL assinada
                        // — ver AvatarUpload.
                        AvatarUpload::field()->columnSpanFull(),
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
                            ->rule(PasswordPolicy::rule())
                            ->same('password_confirmation')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? __('admin.users.password_hint_create', ['rules' => PasswordPolicy::hint()])
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

    /**
     * Colunas da listagem clássica.
     */
    public static function tableColumns(): array
    {
        return [
            self::avatarColumn(),
            AdminColumns::publicCode(),
            // `name` é criptografado em repouso (checklist 12): exibido,
            // mas NÃO pesquisável/ordenável (a coluna é o ciphertext).
            TextColumn::make('name')
                ->label(__('panel.common.name')),
            TextColumn::make('email')
                ->label(__('auth.ui.email'))
                ->searchable(),
            self::statusColumn(),
            // Só o "sim" é sinalizado: um ⊗ vermelho em cada linha comum
            // (a maioria) transforma o estado normal em alarme e é a
            // única cor saturada do painel (crítica de design).
            // O Filament 5 detecta o cast bool do model e trata a coluna
            // como booleana sozinho; null em falseIcon significa "use o
            // padrão" (x-circle). Só `false` remove o ícone de verdade:
            // usuário comum fica em branco, admin recebe o escudo.
            IconColumn::make('is_admin')
                ->label(__('admin.users.admin'))
                ->boolean()
                ->trueIcon(Heroicon::OutlinedShieldCheck)
                ->trueColor('gray')
                ->falseIcon(false)
                ->alignCenter(),
            AdminColumns::dateTime('created_at', __('admin.users.created_at')),
        ];
    }

    /**
     * Modo cards: identidade (nome + e-mail), situação e o selo de admin —
     * o suficiente para reconhecer a conta sem abrir o registro.
     */
    public static function cardComponents(): array
    {
        return [
            Stack::make([
                Split::make([
                    self::avatarColumn()->grow(false),
                    TextColumn::make('name')
                        ->label(__('panel.common.name'))
                        ->weight(FontWeight::SemiBold)
                        ->size(TextSize::Large),
                    IconColumn::make('is_admin')
                        ->label(__('admin.users.admin'))
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedShieldCheck)
                        ->trueColor('gray')
                        ->falseIcon(false)
                        ->grow(false),
                ]),
                TextColumn::make('email')
                    ->label(__('auth.ui.email'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->size(TextSize::Small)
                    ->searchable(),
                Split::make([
                    self::statusColumn(),
                    AdminColumns::publicCode()
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->grow(false),
                ]),
            ])->space(2),
        ];
    }

    /**
     * A foto do usuário na listagem. Sem foto, o mesmo desenho de iniciais
     * do menu do painel (InitialsAvatarProvider, via Filament) — nunca um
     * quadrado vazio, que parece imagem quebrada. A URL vem assinada quando
     * há foto de verdade (Upload::url()).
     */
    private static function avatarColumn(): ImageColumn
    {
        return ImageColumn::make('avatar')
            ->label(__('admin.users.avatar'))
            ->circular()
            ->getStateUsing(fn (User $record): string => Filament::getUserAvatarUrl($record));
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
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
            });
    }

    protected static function tableExtras(Table $table): Table
    {
        return $table
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
                ImageEntry::make('avatar')
                    ->label(__('admin.users.avatar'))
                    ->circular()
                    ->getStateUsing(fn (User $record): string => Filament::getUserAvatarUrl($record)),
                TextEntry::make('codigo_publico')->label(__('admin.common.code')),
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
