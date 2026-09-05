<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;

// =============================================================================
// Layout do painel e da landing no MOBILE (QA bugs 8 e 11).
//
// Antes: `flex-wrap` e mais nada. A 390px a nav empilhava ACIMA da logo e o
// cabeçalho comia 29% da altura da tela em toda página autenticada.
// Agora: a nav some abaixo de sm: e vive num drawer com o mesmo motor do modal
// (Esc, backdrop, foco preso) — o teste valida o contrato do markup.
// =============================================================================

it('painel esconde a nav no mobile e oferece o drawer de navegação', function () {
    $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

    $response
        // Gatilho do hambúrguer, visível só abaixo de sm:.
        ->assertSee('data-modal-open="panel-menu"', false)
        ->assertSee(__('panel.nav.open_menu'))
        // O drawer é um [data-modal] — herda Esc/backdrop/foco do <x-modal>.
        ->assertSee('id="panel-menu"', false)
        ->assertSee('drawer-panel', false)
        // A nav do desktop existe, escondida no mobile.
        ->assertSee('hidden flex-1 items-center gap-1 text-sm sm:flex', false);
});

it('landing esconde a nav no mobile e oferece o drawer de navegação', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-modal-open="landing-menu"', false)
        ->assertSee(__('landing.nav.open_menu'))
        ->assertSee('id="landing-menu"', false)
        ->assertSee('drawer-panel', false);
});

it('drawer do painel repete os mesmos itens de navegação da nav do desktop', function () {
    $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

    // Uma lista, dois markups: cada item aparece duas vezes (desktop + drawer).
    foreach (['dashboard', 'api_keys', 'projects', 'notifications', 'profile'] as $item) {
        expect(substr_count((string) $response->getContent(), __('panel.nav.'.$item)))
            ->toBeGreaterThanOrEqual(2);
    }
});

it('alvos de toque do painel têm no mínimo 44px no mobile', function () {
    // min-h-11 = 2.75rem = 44px (WCAG 2.5.8 / iOS HIG). O <x-button> aplica em
    // todos os tamanhos; sm: pode encolher no desktop, onde o alvo é o mouse.
    $componentes = ['button', 'checkbox', 'toggle', 'dropdown-item'];

    foreach ($componentes as $component) {
        expect((string) file_get_contents(resource_path("views/components/{$component}.blade.php")))
            ->toContain('min-h-11');
    }
});
