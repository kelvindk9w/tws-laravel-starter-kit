<?php

declare(strict_types=1);

use Database\Seeders\DemoAdminSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// =============================================================================
// Bug de QA #6 — a senha demo semeada precisava passar na política do próprio
// app, e a mensagem de erro precisava sair no idioma ativo.
// =============================================================================

/**
 * A MESMA regra usada por RegisterRequest/ResetPasswordRequest/Profile.
 */
function regraDeSenhaDoApp(): Password
{
    return Password::min((int) config('auth.password_rules.min_length', 12))
        ->letters()
        ->mixedCase()
        ->numbers();
}

it('as credenciais demo semeadas passam na política de senha do app', function (string $chave) {
    $senha = (string) config($chave);

    $validator = Validator::make(['password' => $senha], ['password' => regraDeSenhaDoApp()]);

    expect($validator->passes())->toBeTrue(
        "A senha demo de [{$chave}] não passa na própria política do app: "
        .implode(' ', $validator->errors()->all())
    );
})->with(['ui.demo_login.password', 'ui.demo_admin.password']);

it('o usuário demo semeado consegue logar com a senha da config', function () {
    config()->set('ui.demo_login.enabled', true);

    $this->seed(DemoUserSeeder::class);

    $this->post('/login', [
        'email' => config('ui.demo_login.email'),
        'password' => config('ui.demo_login.password'),
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

it('o admin demo semeado consegue logar com a senha da config', function () {
    config()->set('ui.demo_login.enabled', true);

    $this->seed(DemoAdminSeeder::class);

    $this->post('/login', [
        'email' => config('ui.demo_admin.email'),
        'password' => config('ui.demo_admin.password'),
    ])->assertRedirect();

    $this->assertAuthenticated();
});

it('a mensagem de senha fraca sai no idioma ativo, nunca em inglês fixo', function (string $locale, string $trecho) {
    app()->setLocale($locale);

    $validator = Validator::make(['password' => 'senhafraca'], ['password' => regraDeSenhaDoApp()]);

    expect($validator->passes())->toBeFalse();

    $mensagens = implode(' ', $validator->errors()->all());

    expect($mensagens)->toContain($trecho);
})->with([
    ['pt_BR', 'maiúscula'],
    ['en', 'uppercase'],
    ['es', 'mayúscula'],
]);
