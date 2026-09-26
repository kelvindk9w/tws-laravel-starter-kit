<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Kit;

// Módulos opcionais no React: a MESMA detecção do backend (Kit::has) chega ao
// front pelas props compartilhadas; sem o módulo, o front não oferece a tela.

it('as props dizem quais módulos opcionais estão instalados', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('kit.modules', array_combine(Kit::OPTIONAL, array_map(Kit::has(...), Kit::OPTIONAL))));
});

it('módulo ausente aparece como ausente para o front', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('kit.modules.accounts', false)
        ->where('kit.modules.uploads', false));
});

it('sem contas, o menu não tem nenhuma tela de conta', function () {
    Kit::pretendAbsent('accounts', 'uploads');

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups) => collect($groups)
            ->pluck('items')->flatten(1)->pluck('href')
            ->intersect(['/api-keys', '/projects', '/account'])->isEmpty()));
});

it('o /admin é o plugin Filament do pacote (o mesmo do Livewire)', function () {
    $this->get('/admin/login')->assertOk()->assertSee('fi-', false);

    $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin')->assertForbidden();
})->group('admin');

it('super admin entra no /admin (login do painel com o critério do pacote)', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
})->group('admin');
