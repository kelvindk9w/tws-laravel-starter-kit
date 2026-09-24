<?php

declare(strict_types=1);

use App\Demo\Contact\Http\Controllers\ContactController;
use App\Demo\Http\Controllers\LandingV2Controller;
use App\Demo\Http\Controllers\ShowcaseFormDemoController;
use App\Demo\Support\DemoSurface;
use Illuminate\Support\Facades\Route;

// =============================================================================
// Rotas da DEMONSTRAÇÃO do kit. Carregadas pelo DemoServiceProvider dentro do
// grupo `web` — as mesmas URLs, nomes e middlewares de antes da separação.
// =============================================================================

// Landing oficial do kit — a direção "Céu" venceu e virou a home. Página
// pública, sem estado e sem formulário próprio: uma view basta. Os números que
// ela exibe vêm de demo/config/landing.php (nada hardcoded), as strings de
// lang/*/landing.php e os bundles próprios estão registrados no vite.config.js.
Route::view('/', 'landing')->name('landing');

// /v3 foi o endereço dessa mesma página enquanto ela era uma direção em
// avaliação. Links já compartilhados continuam valendo: 301 para a home (nunca
// 404 e nunca uma segunda URL servindo o mesmo conteúdo).
Route::permanentRedirect('v3', '/');

// Landing alternativa "O Rastro" (/v2) — mesma verdade do produto, outra
// direção de arte. Fica ao lado da oficial como conceito.
Route::get('v2', LandingV2Controller::class)->name('landing.v2');

// Formulário de contato da landing (público): honeypot + validação + rate
// limit de rotas sensíveis. E-mail enfileirado para PLATFORM_CONTACT_EMAIL.
Route::post('contato', [ContactController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('contact.store');

// Showcase de componentes UI (documentação viva do kit). Público apenas quando
// habilitado (demo/config/ui.php ← UI_SHOWCASE_ENABLED; padrão: só em local) E
// fora de produção: em APP_ENV=production a vitrine responde 404 mesmo com a
// flag ligada, a não ser que DEMO_ALLOW_IN_PRODUCTION esteja declarado
// (DemoSurface).
//
// A rota continua REGISTRADA nos dois casos, respondendo 404: o rodapé e o menu
// do site geram route('ui.showcase') incondicionalmente, e desregistrar a rota
// derrubaria a home com RouteNotFoundException. 404 (e não 403) porque 403
// confirmaria que a vitrine existe e está a uma flag de distância de abrir.
Route::get('ui', function () {
    abort_unless(DemoSurface::showcaseEnabled(), 404);

    return view('showcase');
})->name('ui.showcase');

// Exemplo funcional do padrão Blade clássico (seção "Padrões de formulário"
// do /ui): POST + redirect + old() + erros. Mesma flag do showcase.
Route::post('ui/form-demo', [ShowcaseFormDemoController::class, 'store'])
    ->middleware('throttle:sensitive')
    ->name('ui.form-demo');
