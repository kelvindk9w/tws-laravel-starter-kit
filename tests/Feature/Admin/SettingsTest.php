<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Settings\Models\Setting;
use App\Core\Settings\SettingsManager;
use App\Filament\Pages\Settings;
use Livewire\Livewire;

// =============================================================================
// Settings editáveis pelo super admin (Fase 6): tabela settings sobrescreve
// o .env em runtime, sem editar arquivo. Somente a whitelist de
// config/settings.php é gravável.
// =============================================================================

it('nega a tela de configurações a não-admin (403)', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/settings')
        ->assertForbidden();
});

it('salva overrides pela UI e o valor efetivo passa a vir do banco', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertOk()
        ->set('data.api_keys_inactivity_months', 6)
        ->set('data.security_rate_limit_api', 120)
        ->call('save')
        ->assertHasNoFormErrors();

    // Os overrides estão gravados na tabela...
    expect(Setting::query()->where('key', 'api_keys.inactivity.months')->exists())->toBeTrue();

    // ...e a leitura efetiva (helper setting()) reflete o banco, não o .env.
    expect(setting('api_keys.inactivity.months'))->toBe(6)
        ->and(setting('security.rate_limit.api'))->toBe(120);
});

it('applyToConfig aplica os overrides por cima do config (boot do provider)', function () {
    $manager = app(SettingsManager::class);
    $manager->set('api_keys.inactivity.months', 9);

    $manager->applyToConfig();

    expect(config('api_keys.inactivity.months'))->toBe(9);
});

it('campo vazio remove o override e volta ao valor do .env', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $original = config('api_keys.inactivity.months');

    app(SettingsManager::class)->set('api_keys.inactivity.months', 12);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('data.api_keys_inactivity_months', null)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::query()->where('key', 'api_keys.inactivity.months')->exists())->toBeFalse()
        ->and(setting('api_keys.inactivity.months'))->toBe($original);
});

it('valida os limites declarados na whitelist', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('data.api_keys_inactivity_months', 999) // acima do máximo (36)
        ->call('save')
        ->assertHasFormErrors(['api_keys_inactivity_months']);

    expect(Setting::query()->count())->toBe(0);
});

it('rejeita chave fora da whitelist (defesa contra gravação arbitrária)', function () {
    app(SettingsManager::class)->set('app.key', 1);
})->throws(InvalidArgumentException::class);
