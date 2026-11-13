<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Twstec\Kit\Auth\Actions\ResetPassword;
use Twstec\Kit\Auth\Enums\SessionEndReason;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Events\SessionsEnded;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Models\SensitiveActionToken;
use Twstec\Kit\Auth\Services\VerificationCodes;
use Twstec\Kit\Auth\Sessions\SessionBinding;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// FIM DE SESSÃO POR QUALQUER CAMINHO → Events\SessionsEnded, NA HORA — numa
// aplicação limpa. É o gancho de que o que vive preso a uma sessão (a chave
// de API de sessão do twstec/kit-accounts) depende para morrer junto.
// =============================================================================

beforeEach(function (): void {
    config(['security.rate_limit.sensitive' => 1000]);
});

function sessionsEndedUser(array $attributes = []): User
{
    return User::fixture(['email_verified_at' => now(), ...$attributes]);
}

/**
 * Entra pelo formulário e liga um vínculo à sessão (como a chave de sessão
 * faz); devolve a referência.
 */
function signInWithBinding(User $user): string
{
    test()->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])->assertRedirect();
    test()->get('/dashboard')->assertOk();

    return SessionBinding::for(session()->driver(), $user);
}

/**
 * @return list<SessionsEnded>
 */
function endedEvents(): array
{
    /** @var list<SessionsEnded> */
    return Event::dispatched(SessionsEnded::class)->map(fn (array $args): SessionsEnded => $args[0])->values()->all();
}

it('o vínculo é aleatório, fica dentro da sessão, sobrevive à troca do id e é de uma pessoa só', function (): void {
    $user = sessionsEndedUser();
    $other = sessionsEndedUser();
    $session = session()->driver();
    $session->start();

    $ref = SessionBinding::for($session, $user);

    expect($ref)->toMatch('/^[0-9a-f]{64}$/')
        // A referência não é o valor guardado (ela não abre nada) nem o id da sessão.
        ->and($session->get(SessionBinding::SESSION_KEY)['token'])->not->toBe($ref)
        ->and($ref)->not->toContain($session->getId())
        ->and(SessionBinding::for($session, $user))->toBe($ref)
        ->and(SessionBinding::current($session, $other))->toBeNull();

    $session->migrate(true);

    expect(SessionBinding::current($session, $user))->toBe($ref);

    // Outra pessoa na mesma sessão ganha outro vínculo.
    expect(SessionBinding::for($session, $other))->not->toBe($ref);

    $session->invalidate();

    expect(SessionBinding::current($session))->toBeNull();
});

it('logout: a sessão que termina é a desta requisição, com o vínculo dela', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);

    Event::fake([SessionsEnded::class]);

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();

    $events = endedEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->reason)->toBe(SessionEndReason::Logout)
        ->and($events[0]->allSessions)->toBeFalse()
        ->and($events[0]->sessionRef)->toBe($ref)
        ->and($events[0]->user->is($user))->toBeTrue()
        ->and($events[0]->ends($ref))->toBeTrue()
        ->and($events[0]->ends(hash('sha256', 'outra-sessao')))->toBeFalse();
});

it('logout de sessão sem nada ligado não encerra vínculo nenhum', function (): void {
    $user = sessionsEndedUser();
    $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta'])->assertRedirect();

    Event::fake([SessionsEnded::class]);

    $this->post('/logout')->assertRedirect();

    $events = endedEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->sessionRef)->toBeNull()
        ->and($events[0]->ends(hash('sha256', 'qualquer')))->toBeFalse();
});

it('sessão derrubada pela troca de credencial em outro aparelho também avisa ao cair', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);

    Auth::forgetGuards();
    $user->fresh()?->forceFill(['two_factor_enabled_at' => now()])->save();

    Event::fake([SessionsEnded::class]);

    // A próxima requisição desta sessão a encerra (EnsureSessionIsCurrent →
    // logoutCurrentDevice): o aviso sai com o vínculo dela.
    $this->get('/dashboard')->assertRedirect(route('login'));

    $events = endedEvents();

    expect($events)->not->toBeEmpty()
        ->and($events[0]->reason)->toBe(SessionEndReason::Logout)
        ->and($events[0]->ends($ref))->toBeTrue();
});

it('"sair dos outros aparelhos": todas menos a atual', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);

    Event::fake([SessionsEnded::class]);

    Auth::logoutOtherDevices('senha-correta');

    $other = collect(endedEvents())->first(fn (SessionsEnded $event): bool => $event->reason === SessionEndReason::OtherDevices);

    expect($other)->not->toBeNull()
        ->and($other->allSessions)->toBeTrue()
        ->and($other->keptSessionRef)->toBe($ref)
        ->and($other->ends($ref))->toBeFalse()
        ->and($other->ends(hash('sha256', 'sessao-do-outro-aparelho')))->toBeTrue();
});

it('troca de senha na própria sessão: todas as outras terminam, a de quem trocou fica', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);

    Event::fake([SessionsEnded::class]);

    /** @var User $current */
    $current = Auth::user();
    $current->forceFill(['password' => 'senha-nova-123'])->save();

    $events = endedEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->reason)->toBe(SessionEndReason::CredentialsChanged)
        ->and($events[0]->keptSessionRef)->toBe($ref)
        ->and($events[0]->ends($ref))->toBeFalse()
        ->and($events[0]->ends(hash('sha256', 'outra')))->toBeTrue();
});

it('redefinição de senha por e-mail (sem ninguém logado): todas as sessões terminam', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);
    Auth::forgetGuards();

    Event::fake([SessionsEnded::class]);

    $status = app(ResetPassword::class)->handle([
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'senha-redefinida-1',
    ]);
    expect(ResetPassword::succeeded($status))->toBeTrue();

    $events = endedEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->reason)->toBe(SessionEndReason::CredentialsChanged)
        ->and($events[0]->keptSessionRef)->toBeNull()
        ->and($events[0]->ends($ref))->toBeTrue();
});

it('conta bloqueada (o bloqueio do /admin, por exemplo): todas as sessões terminam na hora', function (): void {
    $user = sessionsEndedUser();
    $ref = signInWithBinding($user);

    Event::fake([SessionsEnded::class]);

    // Quem bloqueia é outra pessoa: a sessão desta conta não é a da requisição.
    Auth::forgetGuards();
    $user->fresh()?->forceFill(['status' => UserStatus::Blocked])->save();

    $events = endedEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->reason)->toBe(SessionEndReason::AccountDeactivated)
        ->and($events[0]->allSessions)->toBeTrue()
        ->and($events[0]->keptSessionRef)->toBeNull()
        ->and($events[0]->ends($ref))->toBeTrue();

    // Desbloquear não encerra nada.
    Event::fake([SessionsEnded::class]);
    $user->fresh()?->forceFill(['status' => UserStatus::Active])->save();

    expect(endedEvents())->toBe([]);
});

it('mudança que não toca credencial nem status não encerra sessão', function (): void {
    $user = sessionsEndedUser();
    signInWithBinding($user);
    Auth::forgetGuards();

    Event::fake([SessionsEnded::class]);

    $user->fresh()?->forceFill(['name' => 'Outro Nome', 'locale' => 'es'])->save();

    expect(endedEvents())->toBe([]);
});

it('o código só-de-código (CodeConfirmation) nunca vira token de ação sensível', function (): void {
    Mail::fake();
    $user = sessionsEndedUser(['transaction_password' => 'transacao-123', 'transaction_password_set_at' => now()]);
    $this->actingAs($user);

    app(VerificationCodes::class)->issue($user, VerificationPurpose::CodeConfirmation);

    $code = null;
    Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->purpose === VerificationPurpose::CodeConfirmation;
    });

    // O e-mail fala em confirmar a ação, como a confirmação de segurança.
    expect((new VerificationCodeMail('123456', VerificationPurpose::CodeConfirmation))->envelope()->subject)
        ->toBe((new VerificationCodeMail('123456', VerificationPurpose::SensitiveAction))->envelope()->subject);

    $this->postJson('/sensitive-actions/confirm', ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonMissingPath('token');

    expect(SensitiveActionToken::query()->count())->toBe(0);
});
