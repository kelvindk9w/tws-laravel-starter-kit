<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Support\Platform;

// Landing page pública (/) — vitrine do kit. Valida CONTEÚDO (ADR-010).

it('landing responde 200 com as seções-chave renderizadas', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee(platform()->name)
        ->assertSee(__('landing.hero.title'))
        ->assertSee(__('landing.hero.subtitle'))
        ->assertSee(__('landing.stack.heading'))
        ->assertSee(__('landing.hours.heading'))
        ->assertSee(__('landing.features.heading'))
        ->assertSee(__('landing.cta.heading'));
});

it('landing lista a stack real e soma as horas economizadas', function () {
    $response = $this->get('/');

    foreach (['Laravel 13', 'Livewire 4', 'Filament 5', 'Tailwind 4', 'PostgreSQL 18', 'Horizon', 'Pest'] as $tech) {
        $response->assertSee($tech);
    }

    $total = array_sum(array_column(__('landing.hours.items'), 'hours'));

    $response->assertSee(__('landing.hours.total_label'))
        ->assertSee($total.' horas');
});

it('landing renderiza os cards de features com ícones', function () {
    $response = $this->get('/');

    foreach (__('landing.features.items') as $feature) {
        $response->assertSee($feature['title'])->assertSee($feature['description']);
    }
});

it('landing tem links para showcase, login, registro e demo quando deslogado', function () {
    $this->get('/')
        ->assertSee(route('ui.showcase'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertSee(__('landing.hero.cta_demo'))
        ->assertSee(__('landing.nav.hours'))
        ->assertSee('#horas', false);
});

it('landing mostra o screenshot real do painel e o link do repositório', function () {
    config()->set('platform.repo_url', 'https://github.com/tws/tws-laravel-starter-kit');
    app()->forgetInstance(Platform::class); // o singleton tipado lê a config na 1ª resolução

    expect(public_path('img/landing/dashboard.png'))->toBeFile();

    $this->get('/')
        ->assertOk()
        ->assertSee(asset('img/landing/dashboard.png'), false)
        ->assertSee('https://github.com/tws/tws-laravel-starter-kit', false)
        ->assertSee(__('landing.cta.repo'));
});

it('landing sem repo configurado esconde o CTA (nunca URL quebrada)', function () {
    config()->set('platform.repo_url', null);
    app()->forgetInstance(Platform::class);

    $this->get('/')
        ->assertOk()
        ->assertDontSee(__('landing.cta.repo'));
});

it('landing tem formulário de contato, nota de ambiente dev e footer institucional', function () {
    config()->set('platform.company_url', 'https://example.com');
    app()->forgetInstance(Platform::class);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="contato"', false)
        ->assertSee(route('contact.store'), false)
        ->assertSee('name="website"', false) // honeypot anti-spam
        ->assertSee(__('landing.stack.dev_note'))
        ->assertSee(__('landing.footer.rights', ['year' => date('Y'), 'company' => platform()->companyName]))
        ->assertSee('https://example.com', false);
});

it('landing mostra CTA do painel quando autenticado', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee(__('landing.nav.dashboard'));
});

it('toda string da landing passa pelo arquivo de idioma (sem fallback cru)', function () {
    expect(__('landing.hero.title'))->not->toBe('landing.hero.title')
        ->and(__('landing.hours.heading'))->not->toBe('landing.hours.heading')
        ->and(__('landing.cta.heading'))->not->toBe('landing.cta.heading')
        ->and(__('landing.features.items'))->toBeArray()->toHaveCount(12)
        ->and(__('landing.stack.items'))->toBeArray()->toHaveCount(10);
});
