<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Auth\Models\User;
use App\Core\Auth\Services\SensitiveActionService;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Filament\Support\AvatarUpload;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

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
 * - VERIFICAÇÃO EM DUAS ETAPAS: a MESMA preferência do painel do cliente
 *   (TwoFactorLogin), com a MESMA regra — ligar e desligar pedem senha de
 *   transação + código por e-mail (SensitiveActionService) e o token emitido é
 *   consumido pelo próprio TwoFactorLogin. Em dois modais encadeados: senha →
 *   código. A conta demo protegida vê o motivo e o botão desabilitado.
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

    /**
     * Passo 1: senha de transação → código por e-mail. Depois abre o passo 2
     * no lugar deste modal.
     */
    public function toggleTwoFactorAction(): Action
    {
        return Action::make('toggleTwoFactor')
            ->label(fn (): string => $this->twoFactorEnabled()
                ? __('panel.profile.two_factor_disable')
                : __('panel.profile.two_factor_enable'))
            ->color(fn (): string => $this->twoFactorEnabled() ? 'gray' : 'primary')
            ->disabled(fn (): bool => app(TwoFactorLogin::class)->blockedReason($this->user()) !== null)
            ->modalHeading(__('panel.sensitive.heading'))
            ->modalDescription(fn (): string => $this->twoFactorEnabled()
                ? __('panel.profile.two_factor_confirm_disable')
                : __('panel.profile.two_factor_confirm_enable'))
            ->schema([
                TextInput::make('transaction_password')
                    ->label(__('auth.ui.transaction_password_title'))
                    ->helperText(__('panel.sensitive.password_hint'))
                    ->password()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('panel.sensitive.send_code'))
            ->action(function (array $data): void {
                $reason = app(TwoFactorLogin::class)->blockedReason($this->user());

                if ($reason !== null) {
                    $this->refuse($reason);
                }

                try {
                    app(SensitiveActionService::class)->sendCode($this->user(), (string) ($data['transaction_password'] ?? ''));
                } catch (ValidationException $exception) {
                    $this->refuse($this->firstMessage($exception));
                }

                $this->replaceMountedAction('confirmTwoFactor');
            });
    }

    /**
     * Passo 2: código por e-mail → token de ação sensível → liga/desliga.
     */
    public function confirmTwoFactorAction(): Action
    {
        return Action::make('confirmTwoFactor')
            ->modalHeading(__('panel.sensitive.heading'))
            ->modalDescription(__('panel.sensitive.code_hint'))
            ->schema([
                OneTimeCodeInput::make('code')
                    ->label(__('panel.sensitive.code'))
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('panel.sensitive.confirm'))
            ->action(function (array $data): void {
                $twoFactor = app(TwoFactorLogin::class);
                $user = $this->user();
                $enabling = ! $twoFactor->enabledFor($user);

                try {
                    $issued = app(SensitiveActionService::class)->confirmCode($user, (string) ($data['code'] ?? ''));

                    $enabling
                        ? $twoFactor->enable($user, $issued['token'])
                        : $twoFactor->disable($user, $issued['token']);
                } catch (ValidationException $exception) {
                    $this->refuse($this->firstMessage($exception));
                }

                Notification::make()
                    ->success()
                    ->title(__($enabling ? 'auth.two_factor.enabled' : 'auth.two_factor.disabled'))
                    ->send();
            });
    }

    public function twoFactorAvailable(): bool
    {
        return TwoFactorLogin::available();
    }

    public function twoFactorEnabled(): bool
    {
        return app(TwoFactorLogin::class)->enabledFor($this->user());
    }

    public function twoFactorBlockedReason(): ?string
    {
        return app(TwoFactorLogin::class)->blockedReason($this->user());
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

    /**
     * Recusa com o motivo na tela e mantém o modal aberto.
     */
    private function refuse(string $message): never
    {
        Notification::make()->danger()->title($message)->send();

        // Mesmo efeito de $action->halt(), escrito como `throw` para o
        // retorno `never` ficar explícito.
        throw new Halt;
    }

    private function firstMessage(ValidationException $exception): string
    {
        return (string) collect($exception->errors())->flatten()->first();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
