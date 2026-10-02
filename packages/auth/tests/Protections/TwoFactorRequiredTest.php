<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\IdentifiesAdministrators;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Exceptions\TwoFactorRequiredException;
use Twstec\Kit\Auth\Http\Middleware\EnsureAccountIsActive;
use Twstec\Kit\Auth\Http\Middleware\EnsureTwoFactorIsConfigured;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Models\SensitiveActionToken;
use Twstec\Kit\Auth\Providers\AuthServiceProvider;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Auth\Support\AdministratorFlag;
use Twstec\Kit\Auth\Support\PendingTwoFactorLogin;
use Twstec\Kit\Auth\Support\TwoFactorRequirement;
use Twstec\Kit\Auth\Tests\Fixtures\User;
use Twstec\Kit\Foundation\Audit\Enums\AuditOutcome;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

// =============================================================================
// SEGUNDO FATOR OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED) — numa aplicação
// Laravel limpa, sem nada do starter. A barreira é do pacote: o middleware
// que ele anexa ao grupo `web`, a recusa de desligar no TwoFactorLogin e os
// envios da configuração (TwoFactorSetupController).
// =============================================================================

beforeEach(function (): void {
    Mail::fake();
    config([
        'auth.verification.resend_cooldown_seconds' => 0,
        'security.rate_limit.sensitive' => 100,
    ]);
});

/**
 * Conta comum, com e-mail confirmado.
 *
 * @param  array<string, mixed>  $attributes
 */
function requiredTfaUser(array $attributes = []): User
{
    return User::fixture(['email_verified_at' => now(), ...$attributes]);
}

/**
 * Código do último e-mail de código com a finalidade dada.
 */
function requiredTfaCode(VerificationPurpose $purpose = VerificationPurpose::SensitiveAction): string
{
    /** @var VerificationCodeMail $mail */
    $mail = Mail::queued(VerificationCodeMail::class)
        ->filter(fn (VerificationCodeMail $mail): bool => $mail->purpose === $purpose)
        ->last();

    return $mail->code;
}

/**
 * Token de ação sensível emitido pelo fluxo de verdade (senha de transação +
 * código por e-mail).
 */
function requiredTfaToken(User $user): string
{
    $sensitive = app(SensitiveActionService::class);
    $sensitive->sendCode($user, 'Trans4cao!Segura');

    return $sensitive->confirmCode($user, requiredTfaCode())['token'];
}

it('o middleware vem do pacote, no fim do grupo `web`, depois do status da conta', function (): void {
    $this->get('/login')->assertOk();

    $web = $this->app['router']->getMiddlewareGroups()['web'];

    expect($web)->toContain(EnsureTwoFactorIsConfigured::class)
        ->and(AuthServiceProvider::WEB_MIDDLEWARE)->toBe([
            EnsureAccountIsActive::class,
            EnsureTwoFactorIsConfigured::class,
        ]);
});

it('`none` (padrão): conta sem segundo fator entra no painel normalmente', function (): void {
    expect(TwoFactorRequirement::mode())->toBe('none');

    $this->actingAs(requiredTfaUser())->get('/dashboard')->assertOk()->assertSee('painel');
});

it('`all`: sem o segundo fator, nenhuma tela além da configuração — página, formulário, JSON e público', function (): void {
    config(['auth.two_factor.required' => 'all']);
    $user = requiredTfaUser();

    $this->actingAs($user)->get('/dashboard?aba=1')->assertRedirect(route('two-factor.setup'));
    expect(session('url.intended'))->toBe(url('/dashboard?aba=1'));

    // Página pública, envio de formulário e endpoint de sessão em JSON.
    $this->get('/')->assertRedirect(route('two-factor.setup'));
    $this->post('/acao-sensivel')->assertRedirect(route('two-factor.setup'));
    $this->postJson('/sensitive-actions/code', ['transaction_password' => 'x'])
        ->assertForbidden()
        ->assertJson(['message' => __('auth.two_factor.setup_required')]);

    // Um cabeçalho do Livewire não abre nada (a rota do endpoint do Livewire
    // não está na lista do que fica aberto).
    $this->withHeader('X-Livewire', '1')->get('/dashboard')->assertRedirect(route('two-factor.setup'));

    // O que fica aberto: a própria configuração.
    $this->get('/two-factor/setup')->assertOk()->assertSee('tela de configuração do segundo fator');
});

it('`all`: quem já ligou o segundo fator passa', function (): void {
    config(['auth.two_factor.required' => 'all']);

    $this->actingAs(requiredTfaUser(['two_factor_enabled_at' => now()]))->get('/dashboard')->assertOk();
});

it('`all`: e-mail por confirmar vem primeiro (vai ao aviso, não à configuração)', function (): void {
    config(['auth.two_factor.required' => 'all']);

    $this->actingAs(User::fixture(['email_verified_at' => null]))
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice'));
});

it('`admins`: só quem o critério reconhece como administrador é obrigado', function (): void {
    config(['auth.two_factor.required' => 'admins']);

    $admin = requiredTfaUser(['email' => 'admin@example.com']);
    $comum = requiredTfaUser(['email' => 'comum@example.com']);

    // O critério é de quem conhece o painel (o twstec/kit-admin registra o
    // dele); aqui, um critério de teste.
    $this->app->instance(IdentifiesAdministrators::class, new class implements IdentifiesAdministrators
    {
        public function isAdministrator(AuthUser $user): bool
        {
            return $user->email === 'admin@example.com';
        }
    });

    $this->actingAs($comum)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('two-factor.setup'));
});

it('`admins` sem critério registrado: vale a coluna `is_admin`', function (): void {
    $user = requiredTfaUser();

    expect(app(IdentifiesAdministrators::class))->toBeInstanceOf(AdministratorFlag::class)
        ->and((new AdministratorFlag)->isAdministrator($user))->toBeFalse();

    $user->setAttribute('is_admin', true);

    expect((new AdministratorFlag)->isAdministrator($user))->toBeTrue();
});

it('valor desconhecido vale como `all` (falha fechada)', function (string $valor): void {
    config(['auth.two_factor.required' => $valor]);

    expect(TwoFactorRequirement::mode())->toBe('all');

    $this->actingAs(requiredTfaUser())->get('/dashboard')->assertRedirect(route('two-factor.setup'));
})->with(['todos', 'ADMIN', '1', 'true']);

it('as rotas abertas a quem está pendente: sair, senha de transação e os envios da configuração', function (): void {
    config(['auth.two_factor.required' => 'all']);
    $user = requiredTfaUser();

    $this->actingAs($user)->put('/settings/transaction-password', [
        'transaction_password' => 'Trans4cao!Segura',
        'transaction_password_confirmation' => 'Trans4cao!Segura',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->hasTransactionPassword())->toBeTrue();

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});

it('configuração: senha de transação → código → liga, consome o token e volta ao destino guardado', function (): void {
    config(['auth.two_factor.required' => 'all']);
    $user = requiredTfaUser(['transaction_password' => 'Trans4cao!Segura']);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.setup'));

    // Senha de transação errada: recusa, nada é enviado.
    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'errada'])
        ->assertRedirect('/two-factor/setup')
        ->assertSessionHasErrors(['transaction_password' => __('auth.transaction_password.invalid')]);
    Mail::assertNothingQueued();

    $this->from('/two-factor/setup')->post('/two-factor/setup/code', ['transaction_password' => 'Trans4cao!Segura'])
        ->assertRedirect('/two-factor/setup')
        ->assertSessionHas('two_factor_setup.code_sent', true);

    // Código errado: continua sem o segundo fator.
    $this->from('/two-factor/setup')->post('/two-factor/setup', ['code' => '000000'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->two_factor_enabled_at)->toBeNull();

    // O código da configuração é da família própria dela (TwoFactorSetup).
    $this->post('/two-factor/setup', ['code' => requiredTfaCode(VerificationPurpose::TwoFactorSetup)])
        ->assertRedirect(url('/dashboard'))
        ->assertSessionHas('status', __('auth.two_factor.setup_done'));

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(SensitiveActionToken::query()->whereNull('consumed_at')->count())->toBe(0)
        ->and(session()->has('two_factor_setup.code_sent'))->toBeFalse();

    $this->get('/dashboard')->assertOk();
});

it('configuração: sem senha de transação, o envio do código recusa com o motivo', function (): void {
    config(['auth.two_factor.required' => 'all']);

    $this->actingAs(requiredTfaUser())
        ->from('/two-factor/setup')
        ->post('/two-factor/setup/code', ['transaction_password' => 'qualquer'])
        ->assertSessionHasErrors(['two_factor' => __('auth.two_factor.requires_transaction_password')]);

    Mail::assertNothingQueued();
});

it('com a regra valendo, DESLIGAR é recusado no servidor, sem gastar o token, e a recusa vai para a trilha', function (): void {
    config(['auth.two_factor.required' => 'all']);
    $user = requiredTfaUser(['transaction_password' => 'Trans4cao!Segura', 'two_factor_enabled_at' => now()]);
    $this->actingAs($user);

    $token = requiredTfaToken($user);

    expect(fn () => app(TwoFactorLogin::class)->disable($user, $token))
        ->toThrow(TwoFactorRequiredException::class, __('auth.two_factor.required_cannot_disable'));

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(SensitiveActionToken::query()->whereNull('consumed_at')->count())->toBe(1);

    $linha = AuditEvent::query()->sole();

    expect($linha->action)->toBe('user.two_factor_disabled')
        ->and($linha->outcome)->toBe(AuditOutcome::Denied)
        ->and($linha->subject_uuid)->toBe($user->uuid)
        ->and($linha->actor_uuid)->toBe($user->uuid)
        ->and($linha->context->value)->toBe('panel')
        ->and($linha->reason)->toBe(__('auth.two_factor.required_cannot_disable'));

    expect(app(TwoFactorLogin::class)->disableBlockedReason($user))->toBe(__('auth.two_factor.required_cannot_disable'));

    // Sem a regra, o mesmo token desliga.
    config(['auth.two_factor.required' => 'none']);
    app(TwoFactorLogin::class)->disable($user, $token);

    expect($user->fresh()->two_factor_enabled_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe(1);
});

it('`admins`: quem não é administrador continua podendo desligar', function (): void {
    config(['auth.two_factor.required' => 'admins']);
    $user = requiredTfaUser(['transaction_password' => 'Trans4cao!Segura', 'two_factor_enabled_at' => now()]);

    app(TwoFactorLogin::class)->disable($user, requiredTfaToken($user));

    expect($user->fresh()->two_factor_enabled_at)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('a regra torna a opção disponível mesmo com AUTH_TWO_FACTOR_ENABLED=false (o login pede o código)', function (): void {
    config(['auth.two_factor.enabled' => false, 'auth.two_factor.required' => 'all']);

    expect(TwoFactorLogin::available())->toBeTrue();

    $user = requiredTfaUser(['two_factor_enabled_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
    expect(session()->has(PendingTwoFactorLogin::SESSION_KEY))->toBeTrue();
});

it('carência: conta que já existia passa até a data + N dias; depois, configura', function (): void {
    config([
        'auth.two_factor.required' => 'all',
        'auth.two_factor.grace_days' => 10,
        'auth.two_factor.required_since' => '2026-09-01',
    ]);

    $antiga = requiredTfaUser();
    $antiga->forceFill(['created_at' => '2026-08-01 12:00:00'])->save();

    $this->travelTo('2026-09-05 12:00:00');

    expect(app(TwoFactorRequirement::class)->graceEndsAt($antiga)?->toDateString())->toBe('2026-09-11');
    $this->actingAs($antiga)->get('/dashboard')->assertOk();

    // Na carência, desligar já é recusado (a regra vale para ela).
    expect(app(TwoFactorLogin::class)->requiredFor($antiga))->toBeTrue();

    $this->travelTo('2026-09-11 00:00:01');

    expect(app(TwoFactorRequirement::class)->graceEndsAt($antiga))->toBeNull();
    $this->actingAs($antiga)->get('/dashboard')->assertRedirect(route('two-factor.setup'));
});

it('carência: conta criada depois da data da regra configura já', function (): void {
    config([
        'auth.two_factor.required' => 'all',
        'auth.two_factor.grace_days' => 10,
        'auth.two_factor.required_since' => '2026-09-01',
    ]);

    $this->travelTo('2026-09-05 12:00:00');
    $nova = requiredTfaUser();

    $this->actingAs($nova)->get('/dashboard')->assertRedirect(route('two-factor.setup'));
});

it('carência sem data válida não vale, e o pacote avisa no log', function (?string $data): void {
    config([
        'auth.two_factor.required' => 'all',
        'auth.two_factor.grace_days' => 30,
        'auth.two_factor.required_since' => $data,
    ]);

    $antiga = requiredTfaUser();
    $antiga->forceFill(['created_at' => now()->subYear()])->save();

    $this->actingAs($antiga)->get('/dashboard')->assertRedirect(route('two-factor.setup'));

    Log::spy();
    (new AuthServiceProvider($this->app))->boot();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_starts_with($message, 'AUTH_TWO_FACTOR_GRACE_DAYS'));
})->with([null, '', 'não é data']);
