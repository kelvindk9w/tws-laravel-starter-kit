<?php

declare(strict_types=1);

use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Mail\VerificationCodeMail;
use App\Core\Auth\Models\User;
use App\Core\Auth\Verification\Drivers\EmailVerificationDriver;
use App\Core\Localization\Middleware\SetLocale;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// i18n (ADR-007): middleware SetLocale, rota de troca e preferência no perfil.
// Prioridade: conta logada → cookie do visitante → padrão da plataforma.

it('visitante sem cookie vê o padrão da plataforma (pt-BR)', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('landing.hero.title_line_2', locale: 'pt_BR'));
});

it('visitante com cookie vê o idioma escolhido', function (string $locale, string $expected) {
    // withCookie: o harness criptografa (como o EncryptCookies faria na borda).
    $this->withCookie(SetLocale::COOKIE, $locale)
        ->get('/')
        ->assertOk()
        ->assertSee($expected);
})->with([
    'en' => ['en', 'The base your AI'],
    'es' => ['es', 'La base que tu IA'],
]);

it('cookie com locale fora da whitelist cai no padrão da plataforma', function () {
    $this->withCookie(SetLocale::COOKIE, 'fr')
        ->get('/')
        ->assertOk()
        ->assertSee(__('landing.hero.title_line_2', locale: 'pt_BR'));
});

it('usuário logado com preferência salva vê o painel no idioma dela', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Hola,');
});

it('preferência da conta vence o cookie do visitante', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->withCookie(SetLocale::COOKIE, 'es')
        ->get('/')
        ->assertOk()
        ->assertSee(__('landing.hero.title_line_2', locale: 'en'));
});

it('rota de troca grava o cookie e redireciona de volta', function () {
    $response = $this->get('/locale/en');

    $response->assertRedirect('/')
        ->assertCookie(SetLocale::COOKIE, 'en');
});

it('rota de troca rejeita locale fora da whitelist', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('rota de troca persiste a preferência na conta do usuário logado', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)->get('/locale/es')->assertRedirect('/');

    expect($user->fresh()->locale)->toBe('es');
});

it('perfil salva a preferência de idioma junto dos dados básicos', function () {
    $user = User::factory()->create(['locale' => null]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'Maria Silva')
        ->set('locale', 'en')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->locale)->toBe('en');
});

it('perfil rejeita idioma fora da whitelist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('locale', 'fr')
        ->call('updateProfile')
        ->assertHasErrors(['locale']);
});

// O seletor é um dropdown de LINKS reais para locale.switch (era um <select>
// nativo com bandeira em emoji): o contrato é a URL de cada idioma + o nome.
it('seletor de idioma aparece na landing e no painel com um link por idioma', function () {
    $landing = $this->get('/')->assertOk();
    $painel = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

    foreach (platform()->availableLocales as $locale) {
        $landing->assertSee(route('locale.switch', $locale), false)
            ->assertSee(__("ui.locale.names.{$locale}"));

        $painel->assertSee(route('locale.switch', $locale), false);
    }
});

it('e-mail transacional sai no locale do destinatário', function () {
    Mail::fake();

    $user = User::factory()->create(['locale' => 'es']);

    app(EmailVerificationDriver::class)->send($user, '123456', VerificationPurpose::SensitiveAction);

    Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail): bool => $mail->locale === 'es');
});

it('preferredLocale cai no padrão da plataforma sem preferência válida', function () {
    expect(User::factory()->create(['locale' => null])->preferredLocale())->toBe(platform()->locale)
        ->and(User::factory()->create(['locale' => 'fr'])->preferredLocale())->toBe(platform()->locale)
        ->and(User::factory()->create(['locale' => 'en'])->preferredLocale())->toBe('en');
});

it('todos os idiomas disponíveis têm os mesmos arquivos e chaves do pt-BR', function () {
    $reference = [];

    foreach (glob(lang_path('pt_BR/*.php')) as $file) {
        $reference[basename($file)] = collect(require $file)->dot()->keys()->sort()->values();
    }

    foreach (['en', 'es'] as $locale) {
        foreach ($reference as $file => $keys) {
            $path = lang_path("{$locale}/{$file}");

            expect($path)->toBeFile();

            $actual = collect(require $path)->dot()->keys()->sort()->values();

            expect($actual->all())->toBe($keys->all(), "lang/{$locale}/{$file} diverge do pt-BR");
        }
    }
});
