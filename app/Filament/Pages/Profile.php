<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Auth\Models\User;
use App\Filament\Support\AvatarUpload;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Perfil do super admin (/admin — demo-safe).
 *
 * - FOTO: editável — sobe pela função global de upload do kit
 *   (AvatarUpload → SecureUploadService) e aparece na hora no avatar do
 *   cabeçalho, porque o InitialsAvatarProvider lê o mesmo vínculo.
 * - NOME: editável e funcional (grava na conta logada).
 * - E-MAIL: read-only com nota explicativa — mudar o e-mail da conta demo
 *   quebraria o login para os próximos visitantes.
 * - SENHA: seção MONTADA mas sem endpoint — campo de senha atual
 *   desabilitado (preview) com a nota "indisponível na demo". Nenhum
 *   campo da seção é desidratado: nada aqui pode derrubar o acesso demo.
 */
final class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.profile';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user?->name,
            'email' => $user?->email,
            'avatar' => AvatarUpload::stateFor($user),
        ]);
    }

    public function getTitle(): string
    {
        return __('admin.profile.heading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AvatarUpload::field()->label(__('panel.profile.avatar_heading')),
                TextInput::make('name')
                    ->label(__('panel.common.name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('email')
                    ->label(__('auth.ui.email'))
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(__('admin.profile.email_readonly_note')),
                Section::make(__('admin.profile.password_section'))
                    ->description(__('admin.profile.password_note'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('admin.profile.current_password'))
                            ->password()
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array{name: string, avatar?: mixed} $state */
        $state = $this->form->getState();

        /** @var User $user */
        $user = auth()->user();

        $user->forceFill(['name' => $state['name']])->save();

        AvatarUpload::applyTo($user, $state['avatar'] ?? null);

        Notification::make()
            ->success()
            ->title(__('admin.profile.saved'))
            ->send();
    }
}
