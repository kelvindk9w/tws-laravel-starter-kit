<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;

// i18n do super admin (/admin — Filament): o painel segue a MESMA resolução
// de locale do app (SetLocale: preferência da conta → cookie → padrão) e
// expõe o seletor compacto (bandeira + sigla) na topbar.

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
});

it('renderiza o admin em pt-BR por padrão (fallback da plataforma)', function () {
    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.users.plural', [], 'pt_BR'));
});

it('renderiza o admin em inglês quando o cookie locale=en está presente', function () {
    $this->actingAs($this->admin)
        ->withCookie('locale', 'en')
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.users.plural', [], 'en'));
});

it('renderiza o admin em espanhol quando o cookie locale=es está presente', function () {
    $this->actingAs($this->admin)
        ->withCookie('locale', 'es')
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.users.plural', [], 'es'));
});

it('a preferência da conta (users.locale) vence o cookie no admin', function () {
    $this->admin->forceFill(['locale' => 'es'])->save();

    $this->actingAs($this->admin)
        ->withCookie('locale', 'en')
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.users.plural', [], 'es'));
});

it('ignora locale fora da whitelist e cai no padrão', function () {
    $this->actingAs($this->admin)
        ->withCookie('locale', 'fr')
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.users.plural', [], 'pt_BR'));
});

it('expõe o seletor de idioma compacto (bandeira + sigla) na topbar do admin', function () {
    $response = $this->actingAs($this->admin)->get('/admin')->assertOk();

    $response->assertSee('🇧🇷 PT', false)
        ->assertSee('🇺🇸 EN', false)
        ->assertSee('🇪🇸 ES', false)
        ->assertSee(route('locale.switch', 'en'), false);
});

it('traduz as strings do admin.php nos três idiomas (paridade de chaves)', function (string $locale, string $expected) {
    app()->setLocale($locale);

    expect(__('admin.users.plural'))->toBe($expected)
        ->and(__('admin.nav.group_security'))->not->toBe('admin.nav.group_security');
})->with([
    ['pt_BR', 'Usuários'],
    ['en', 'Users'],
    ['es', 'Usuarios'],
]);
