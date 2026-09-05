<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Localization\Middleware\SetLocale;
use App\Core\Support\Platform;

// Landing "Céu" (v3) — rota /v3. Valida CONTEÚDO, não só status (ADR-010).

it('responde 200 e renderiza as cinco seções da página', function () {
    $this->get('/v3')
        ->assertOk()
        ->assertSee(__('landing_v3.hero.title_line_1'))
        ->assertSee(__('landing_v3.hero.title_line_2'))
        ->assertSee(__('landing_v3.hero.subtitle'))
        ->assertSee(__('landing_v3.components.title'))
        ->assertSee(__('landing_v3.how.title'))
        ->assertSee(__('landing_v3.security.title'))
        ->assertSee(__('landing_v3.footer.title'));
});

it('tem rota nomeada e o mesmo conteúdo por ela', function () {
    expect(route('landing.v3', absolute: false))->toBe('/v3');

    $this->get(route('landing.v3'))->assertOk();
});

it('mostra as quatro telas reais do kit, em versão clara e escura', function () {
    $response = $this->get('/v3');

    foreach (['dashboard', 'admin', 'ui', 'login'] as $screen) {
        foreach (['light', 'dark'] as $theme) {
            expect(public_path("img/landing-v3/{$screen}-{$theme}-1440.webp"))->toBeFile()
                ->and(public_path("img/landing-v3/{$screen}-{$theme}-720.webp"))->toBeFile();
        }

        $response->assertSee(asset("img/landing-v3/{$screen}-light-1440.webp"), false)
            ->assertSee(asset("img/landing-v3/{$screen}-dark-1440.webp"), false)
            ->assertSee(__("landing_v3.hero.screens.{$screen}.alt"));
    }
});

it('mostra o split-screen com o código do x-table e a captura da tela renderizada', function () {
    expect(public_path('img/landing-v3/table-light-900.webp'))->toBeFile()
        ->and(public_path('img/landing-v3/table-dark-900.webp'))->toBeFile();

    $this->get('/v3')
        ->assertSee('data-v3-split-range', false)
        ->assertSee(asset('img/landing-v3/table-light-900.webp'), false)
        ->assertSee(__('landing_v3.components.code_label'))
        ->assertSee(__('landing_v3.components.screen_label'))
        // O trecho de código é o USO REAL do componente do kit.
        ->assertSee('&lt;x-table', false)
        ->assertSee('&lt;x-table-cell', false);
});

it('lista as três etapas com etiqueta de cursor e comando de terminal', function () {
    $response = $this->get('/v3');

    foreach (__('landing_v3.how.steps') as $step) {
        $response->assertSee($step['title'])
            ->assertSee($step['text'])
            ->assertSee($step['cursor'])
            ->assertSee($step['command']);
    }
});

it('lista as seis peças de segurança no bento', function () {
    $response = $this->get('/v3');

    $items = __('landing_v3.security.items');

    expect($items)->toBeArray()->toHaveCount(6);

    foreach ($items as $item) {
        $response->assertSee($item['title'])->assertSee($item['text']);
    }

    $response->assertSee('data-v3-bento', false);
});

it('desenha o arco das oito tecnologias com nome visível (fallback do 3D)', function () {
    $response = $this->get('/v3');

    $tech = __('landing_v3.footer.tech');

    expect($tech)->toBeArray()->toHaveCount(8);

    foreach ($tech as $label) {
        $response->assertSee($label);
    }

    // O 3D levanta as MARCAS deste mesmo bloco: sem ele, não há textura.
    $response->assertSee('id="v3-arc-marks"', false)
        ->assertSee('data-v3-layout="arc"', false);
});

it('usa os números do config, nunca hardcoded na view', function () {
    config()->set('landing_v3.tests', 1234);
    config()->set('landing_v3.clones', 4321);

    // O número da prova social conta 0 → N: ele mora num <span data-v3-count>
    // dentro da pílula âmbar, ao lado (nunca dentro) da frase traduzida.
    $this->get('/v3')
        ->assertOk()
        ->assertSee('data-v3-count="4321"', false)
        ->assertSee('>4.321</span>+', false)
        // Com clones no .env, é esse o número — e a frase muda junto.
        ->assertSee(__('landing_v3.hero.proof_clones'))
        ->assertDontSee(__('landing_v3.hero.proof_tests'))
        // O chip do leque NÃO repete o número: traz outro fato.
        ->assertSee(__('landing_v3.hero.chip_tenancy'));
});

it('sem clones, a prova social é a suíte verde — nunca um número inventado', function () {
    // Uma pílula com "0 desenvolvedores já clonaram" é uma confissão. Sem esse
    // dado, a prova que o kit TEM é a suíte de testes: um fato do projeto.
    config()->set('landing_v3.clones', 0);
    config()->set('landing_v3.tests', 589);

    $this->get('/v3')
        ->assertOk()
        ->assertSee(__('landing_v3.hero.proof_tests'))
        ->assertDontSee(__('landing_v3.hero.proof_clones'))
        ->assertSee('data-v3-count="589"', false);
});

it('aponta o CTA para o repositório quando configurado', function () {
    config()->set('platform.repo_url', 'https://github.com/tws/tws-laravel-starter-kit');
    app()->forgetInstance(Platform::class);

    $this->get('/v3')
        ->assertOk()
        ->assertSee('https://github.com/tws/tws-laravel-starter-kit', false)
        ->assertSee(__('landing_v3.hero.cta_primary'))
        ->assertSee(__('landing_v3.footer.cta'));
});

it('sem repositório configurado o CTA cai em criar conta (nunca URL quebrada)', function () {
    config()->set('platform.repo_url', null);
    app()->forgetInstance(Platform::class);

    $this->get('/v3')
        ->assertOk()
        ->assertSee(__('landing_v3.hero.cta_primary'))
        ->assertSee(route('register'), false);
});

it('reaproveita o cabeçalho e o rodapé do kit (variantes, não cópias)', function () {
    $this->get('/v3')
        ->assertOk()
        // Cabeçalho: a pílula flutuante é o MESMO <x-site-header>.
        ->assertSee('v3-nav-shell', false)
        ->assertSee(platform()->name)
        ->assertSee(route('login'), false)
        // Rodapé institucional do kit, dentro do céu.
        ->assertSee(__('landing.footer.tagline'))
        ->assertSee(__('landing.footer.rights', ['year' => date('Y'), 'company' => platform()->companyName]));
});

it('carrega os bundles próprios da v3 e nenhum script de CDN', function () {
    $html = $this->get('/v3')->assertOk()->getContent();

    expect($html)->toContain('landing-v3')
        ->and($html)->not->toContain('https://cdn')
        ->and($html)->not->toContain('unpkg.com')
        ->and($html)->not->toContain('fonts.googleapis.com');
});

it('respeita a chave que desliga o WebGL', function () {
    config()->set('landing_v3.webgl_enabled', false);

    $this->get('/v3')->assertOk()->assertSee('data-v3-webgl="off"', false);
});

it('toda string da v3 passa pelo arquivo de idioma (sem chave crua na tela)', function () {
    $html = $this->get('/v3')->assertOk()->getContent();

    expect($html)->not->toContain('landing_v3.')
        ->and(__('landing_v3.hero.title_line_1'))->not->toBe('landing_v3.hero.title_line_1')
        ->and(__('landing_v3.components.items'))->toBeArray()->toHaveCount(3)
        ->and(__('landing_v3.how.steps'))->toBeArray()->toHaveCount(3);
});

it('fala os três idiomas do kit', function (string $locale) {
    $this->withCookie(SetLocale::COOKIE, $locale)
        ->get('/v3')
        ->assertOk()
        ->assertSee(__('landing_v3.hero.title_line_1', locale: $locale))
        ->assertSee(__('landing_v3.security.title', locale: $locale));
})->with(['pt_BR', 'en', 'es']);

it('os três idiomas têm exatamente as mesmas chaves', function () {
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = array_merge($keys, is_array($value) ? $flatten($value, $path) : [$path]);
        }

        return $keys;
    };

    $reference = $flatten(require lang_path('pt_BR/landing_v3.php'));

    foreach (['en', 'es'] as $locale) {
        expect($flatten(require lang_path("{$locale}/landing_v3.php")))
            ->toEqualCanonicalizing($reference);
    }
});

it('não deixa a landing atual nem o painel mudarem de forma por causa da v3', function () {
    // As variantes do cabeçalho/rodapé são ADITIVAS: o padrão continua a barra
    // colada no topo com a linha embaixo. Se isto quebrar, a v3 vazou.
    $this->get('/')
        ->assertOk()
        ->assertSee('sticky top-0 z-40 border-b border-border', false)
        ->assertDontSee('v3-nav-shell', false);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('v3-nav-shell', false);
});
