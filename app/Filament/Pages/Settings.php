<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Settings\SettingsManager;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Configurações do sistema (super admin — Fase 6): ajustes operacionais
 * editáveis pela UI, SEM tocar no .env (ADR-006/007).
 *
 * Somente a whitelist de config/settings.php aparece aqui. Campo VAZIO =
 * volta ao valor do .env (o override da tabela settings é removido). Os
 * valores gravados passam a valer no próximo request (o
 * SettingsServiceProvider os aplica no boot, via cache).
 */
final class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_system');
    }

    public function getTitle(): string
    {
        return __('admin.settings.heading');
    }

    public function getSubheading(): string
    {
        return __('admin.settings.subheading');
    }

    public function mount(SettingsManager $settings): void
    {
        $overrides = $settings->all();

        $state = [];

        foreach (array_keys($settings->whitelist()) as $key) {
            // Vazio quando NÃO há override — o placeholder mostra o valor do .env.
            $state[self::fieldName($key)] = $overrides[$key] ?? null;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        /** @var SettingsManager $settings */
        $settings = app(SettingsManager::class);

        $fields = [];

        foreach ($settings->whitelist() as $key => $meta) {
            $fields[] = TextInput::make(self::fieldName($key))
                ->label(__('admin.settings.key_'.self::fieldName($key)))
                ->helperText(__('admin.settings.env_fallback', ['value' => config($key)]))
                ->numeric()
                ->minValue($meta['min'])
                ->maxValue($meta['max'])
                ->nullable();
        }

        return $schema->components($fields)->statePath('data');
    }

    public function save(SettingsManager $settings): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        foreach (array_keys($settings->whitelist()) as $key) {
            $value = $state[self::fieldName($key)] ?? null;

            // Vazio = remove o override (volta ao .env). Valor = grava.
            $settings->set($key, $value === null || $value === '' ? null : (int) $value);
        }

        Notification::make()
            ->success()
            ->title(__('admin.settings.saved'))
            ->send();
    }

    /**
     * Nome do campo no formulário (pontos da chave de config viram "_" —
     * o Filament interpreta pontos como aninhamento de array).
     */
    private static function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
