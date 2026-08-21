<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

// Showcase de componentes (/ui) — kill switch UI_SHOWCASE_ENABLED (config/ui.php).

it('showcase responde 200 quando habilitado e renderiza todas as categorias', function () {
    config()->set('ui.showcase_enabled', true);

    $response = $this->get('/ui');

    $response->assertOk()
        ->assertSee(__('showcase.title'))
        ->assertSee(route('ui.showcase'), false);

    foreach (__('showcase.categories') as $anchor => $label) {
        $response->assertSee($label)->assertSee('id="'.$anchor.'"', false);
    }
});

it('showcase renderiza os componentes reais do kit', function () {
    config()->set('ui.showcase_enabled', true);

    $this->get('/ui')
        ->assertOk()
        ->assertSee(__('showcase.buttons.primary'))
        ->assertSee(__('showcase.alerts.success_title'))
        ->assertSee(__('showcase.forms.with_error_message'))
        ->assertSee(__('showcase.empty_state.title'))
        ->assertSee(__('showcase.modal.title'))
        ->assertSee('data-modal-open', false)
        ->assertSee('animate-spin', false);
});

it('showcase renderiza snippets copiáveis, toggle de tema e scrollspy', function () {
    config()->set('ui.showcase_enabled', true);

    $this->get('/ui')
        ->assertOk()
        ->assertSee('data-copy', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee('data-scrollspy', false)
        ->assertSee('data-toast-show', false)
        ->assertSee('clipboard-toast', false);

    // JS de UI fica em resources/js (Vite). A ÚNICA exceção inline é o script
    // anti-flash de tema no <head> (partials/theme-script) — deliberado,
    // coberto pela CSP base ('unsafe-inline' em script-src, config/security.php).
    $html = $this->get('/ui')->getContent();

    expect(substr_count((string) $html, '<script>'))->toBe(1)
        ->and((string) $html)->toContain('prefers-color-scheme');
});

it('showcase responde 404 quando desabilitado', function () {
    config()->set('ui.showcase_enabled', false);

    $this->get('/ui')->assertNotFound();
});

it('componentes Blade do kit existem e renderizam', function (string $component, string $markup, string $expected) {
    expect(view('components.'.$component)->getPath())->toBeFile();

    $html = Blade::render($markup);

    expect($html)->toContain($expected);
})->with([
    'button' => ['button', '<x-button>OK</x-button>', 'bg-(--brand)'],
    'alert' => ['alert', '<x-alert type="success">OK</x-alert>', 'role="alert"'],
    'badge' => ['badge', '<x-badge color="green">OK</x-badge>', 'rounded-full'],
    'input' => ['input', '<x-input label="Nome" name="nome" />', 'name="nome"'],
    'select' => ['select', '<x-select label="Plano" name="plano"><option>A</option></x-select>', '<select'],
    'checkbox' => ['checkbox', '<x-checkbox label="Aceito" name="termos" />', 'type="checkbox"'],
    'toggle' => ['toggle', '<x-toggle label="2FA" name="tfa" />', 'peer'],
    'card' => ['card', '<x-card title="T">corpo</x-card>', 'rounded-xl'],
    'modal' => ['modal', '<x-modal id="m" title="T">corpo</x-modal>', 'data-modal'],
    'toast' => ['toast', '<x-toast type="success">OK</x-toast>', 'role="status"'],
    'empty-state' => ['empty-state', '<x-empty-state title="Vazio" />', 'border-dashed'],
    'snippet' => ['snippet', '<x-snippet code="php artisan inspire" />', 'data-copy'],
    'spinner' => ['spinner', '<x-spinner />', 'animate-spin'],
    'ui-icon' => ['ui-icon', '<x-ui-icon name="key" />', '<svg'],
]);
