<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Models\VerificationCode;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// O código da CONFIGURAÇÃO do segundo fator obrigatório é de família própria
// (VerificationPurpose::TwoFactorSetup), separada da confirmação de segurança
// (SensitiveAction):
//
// - quem acabou de configurar não espera o intervalo de reenvio para a
//   primeira ação sensível — que continua pedindo senha de transação e um
//   código NOVO (nada é dispensado);
// - o código de uma família não vale na outra;
// - dentro de cada família, o intervalo de reenvio continua valendo.
//
// O intervalo é o padrão (60 s) e o relógio fica parado no segundo: o teste
// não depende de quanto tempo a máquina leva.
// =============================================================================

beforeEach(function (): void {
    Mail::fake();
    config([
        'auth.verification.resend_cooldown_seconds' => 60,
        'security.rate_limit.sensitive' => 100,
        'auth.two_factor.required' => 'all',
    ]);
    $this->freezeSecond();
});

function setupFamilyUser(): User
{
    return User::fixture(['email_verified_at' => now(), 'transaction_password' => 'Trans4cao!Segura']);
}

/**
 * Códigos enviados (na ordem) com a finalidade dada.
 *
 * @return list<string>
 */
function setupFamilyCodes(VerificationPurpose $purpose): array
{
    return Mail::queued(VerificationCodeMail::class)
        ->filter(fn (VerificationCodeMail $mail): bool => $mail->purpose === $purpose)
        ->map(fn (VerificationCodeMail $mail): string => $mail->code)
        ->values()
        ->all();
}

it('a configuração manda o código na família própria — e o e-mail é o de confirmar a ação', function (): void {
    $user = setupFamilyUser();

    $this->actingAs($user)->from('/two-factor/setup')
        ->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertSessionHasNoErrors();

    expect(setupFamilyCodes(VerificationPurpose::TwoFactorSetup))->toHaveCount(1)
        ->and(setupFamilyCodes(VerificationPurpose::SensitiveAction))->toBe([])
        ->and(VerificationCode::query()->sole()->purpose)->toBe(VerificationPurpose::TwoFactorSetup);

    $mail = new VerificationCodeMail('482913', VerificationPurpose::TwoFactorSetup);

    expect($mail->envelope()->subject)->toBe(__('mail.verification_code.subject', ['platform' => platform()->name]));
});

it('logo depois de configurar, a primeira confirmação de segurança manda o código na hora — ainda com senha de transação e código novo', function (): void {
    $user = setupFamilyUser();
    $this->actingAs($user);

    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura']);
    $this->post('/two-factor/setup', ['code' => setupFamilyCodes(VerificationPurpose::TwoFactorSetup)[0]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('auth.two_factor.setup_done'));

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();

    // No MESMO segundo: a senha de transação errada continua recusada…
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'errada'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transaction_password' => __('auth.transaction_password.invalid')]);

    // …e a certa manda o código, sem "aguarde N segundos".
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertOk()
        ->assertJsonPath('message', __('auth.verification_code.sent'));

    $codes = setupFamilyCodes(VerificationPurpose::SensitiveAction);
    expect($codes)->toHaveCount(1);

    // O código da confirmação é NOVO (o da configuração já foi usado e é de
    // outra família): só ele emite o token.
    $this->postJson('/sensitive-actions/confirm', ['code' => $codes[0]])
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_at']);
});

it('o código da configuração não vale na confirmação de segurança, e o da confirmação não liga o segundo fator', function (): void {
    $user = setupFamilyUser();
    $this->actingAs($user);
    $sensitive = app(SensitiveActionService::class);

    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura']);
    $setupCode = setupFamilyCodes(VerificationPurpose::TwoFactorSetup)[0];

    // O código da configuração, apresentado à confirmação de segurança: não
    // há código vivo daquela família — nenhum token.
    expect(fn () => $sensitive->confirmCode($user, $setupCode))
        ->toThrow(ValidationException::class, __('auth.verification_code.expired'));

    // Um código da confirmação de segurança, apresentado à configuração: recusado.
    $sensitive->sendCode($user, 'Trans4cao!Segura');
    $sensitiveCode = setupFamilyCodes(VerificationPurpose::SensitiveAction)[0];

    $this->from('/two-factor/setup')->post('/two-factor/setup', ['code' => $sensitiveCode === $setupCode ? '000000' : $sensitiveCode])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->two_factor_enabled_at)->toBeNull();

    // O código certo da configuração continua valendo (a tentativa na outra
    // família não o gastou) e liga.
    $this->post('/two-factor/setup', ['code' => $setupCode])->assertSessionHasNoErrors();
    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();
});

it('dentro de cada família, o intervalo de reenvio continua valendo', function (): void {
    $user = setupFamilyUser();
    $this->actingAs($user);

    // Configuração: o segundo pedido no mesmo segundo é recusado com o tempo.
    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertSessionHasNoErrors();
    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertSessionHasErrors(['transaction_password' => __('auth.verification_code.resend_cooldown', ['seconds' => 60])]);

    expect(setupFamilyCodes(VerificationPurpose::TwoFactorSetup))->toHaveCount(1);

    $this->post('/two-factor/setup', ['code' => setupFamilyCodes(VerificationPurpose::TwoFactorSetup)[0]])->assertSessionHasNoErrors();

    // Confirmação de segurança: o primeiro sai; o segundo, no mesmo segundo, espera.
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'Trans4cao!Segura'])->assertOk();
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transaction_password' => __('auth.verification_code.resend_cooldown', ['seconds' => 60])]);

    expect(setupFamilyCodes(VerificationPurpose::SensitiveAction))->toHaveCount(1);
});
