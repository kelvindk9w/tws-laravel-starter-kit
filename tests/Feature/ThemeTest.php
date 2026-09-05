<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;

// Tema claro/escuro/sistema: script sem flash, toggle de 3 estados e
// persistência na conta (users.theme).

it('layouts públicos e do painel carregam o script anti-flash e o default do tema', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme-default="system"', false)
        ->assertSee('prefers-color-scheme', false);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('prefers-color-scheme', false)
        ->assertSee('data-authenticated', false)
        ->assertSee('csrf-token', false);
});

it('data-theme-default reflete a preferência salva na conta', function () {
    $user = User::factory()->create(['theme' => 'dark']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-theme-default="dark"', false);
});

// O seletor de tema é um DROPDOWN com os 3 estados nomeados (era um ícone que
// ciclava às cegas): o contrato verificado é o item de cada estado, com rótulo.
it('seletor de tema com os 3 estados nomeados aparece na landing, showcase e painel', function () {
    foreach (['system', 'light', 'dark'] as $state) {
        $this->get('/')->assertOk()->assertSee('data-theme-set="'.$state.'"', false);
    }

    $this->get('/')->assertOk()->assertSee(__('ui.theme.dark'));

    config()->set('ui.showcase_enabled', true);
    $this->get('/ui')->assertOk()->assertSee('data-theme-set="dark"', false);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-theme-set="dark"', false)
        ->assertSee(__('ui.theme.system'));
});

it('perfil exibe o segmented control de aparência', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertOk()
        ->assertSee(__('panel.profile.theme_heading'))
        ->assertSee('data-theme-set="system"', false)
        ->assertSee('data-theme-set="light"', false)
        ->assertSee('data-theme-set="dark"', false);
});

it('rota de preferência de tema persiste na conta', function (string $theme) {
    $user = User::factory()->create(['theme' => null]);

    $this->actingAs($user)
        ->postJson(route('settings.theme'), ['theme' => $theme])
        ->assertOk()
        ->assertJson(['theme' => $theme]);

    expect($user->fresh()->theme)->toBe($theme);
})->with(['light', 'dark', 'system']);

it('rota de preferência de tema rejeita valores inválidos', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('settings.theme'), ['theme' => 'neon'])
        ->assertUnprocessable();
});

it('rota de preferência de tema exige login', function () {
    $this->postJson(route('settings.theme'), ['theme' => 'dark'])
        ->assertUnauthorized();
});
