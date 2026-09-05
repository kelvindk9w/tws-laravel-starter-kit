<?php

declare(strict_types=1);

namespace App\Filament\Pages;

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
        /** @var array{name: string} $state */
        $state = $this->form->getState();

        auth()->user()->forceFill(['name' => $state['name']])->save();

        Notification::make()
            ->success()
            ->title(__('admin.profile.saved'))
            ->send();
    }
}
