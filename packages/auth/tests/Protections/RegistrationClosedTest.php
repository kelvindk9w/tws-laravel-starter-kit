<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twstec\Kit\Auth\Actions\RegisterUser;
use Twstec\Kit\Auth\Support\Registration;
use Twstec\Kit\Auth\Tests\Fixtures\User;

// =============================================================================
// CADASTRO PÚBLICO FECHADO (AUTH_REGISTRATION_ENABLED=false) — numa aplicação
// limpa. A rota de envio é a do controller do pacote, sem nenhum middleware
// declarado no TestCase: o 404 vem do pacote.
// =============================================================================

beforeEach(function (): void {
    Notification::fake();
    config(['security.rate_limit.sensitive' => 100]);
});

it('aberto por padrão', function (): void {
    expect(Registration::enabled())->toBeTrue();
});

it('fechado: o envio responde 404 antes da validação e nenhuma conta nasce', function (array $dados): void {
    config(['auth.registration.enabled' => false]);

    $this->post('/register', $dados)->assertNotFound();
    $this->postJson('/register', $dados)->assertNotFound();

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
})->with([
    'dados válidos' => [['name' => 'Nova Pessoa', 'email' => 'nova@example.com', 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123']],
    'dados inválidos' => [['email' => 'não-é-email']],
]);

it('fechado: e-mail já cadastrado não vira oráculo (o mesmo 404)', function (): void {
    User::fixture(['email' => 'existe@example.com']);
    config(['auth.registration.enabled' => false]);

    $this->post('/register', ['name' => 'X', 'email' => 'existe@example.com', 'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123'])
        ->assertNotFound()
        ->assertSessionHasNoErrors();
});

it('fechado: a Action também recusa (um front que a chame direto não reabre o cadastro)', function (): void {
    config(['auth.registration.enabled' => false]);

    $request = Request::create('/qualquer', 'POST');
    $request->setLaravelSession($this->app['session.store']);

    expect(fn () => app(RegisterUser::class)->handle($request, ['name' => 'X', 'email' => 'x@example.com', 'password' => 'SenhaForte123']))
        ->toThrow(NotFoundHttpException::class);

    expect(User::query()->count())->toBe(0);
});

it('o interruptor entende os valores de texto do .env', function (mixed $valor, bool $aberto): void {
    config(['auth.registration.enabled' => $valor]);

    expect(Registration::enabled())->toBe($aberto);
})->with([
    [false, false],
    ['false', false],
    ['0', false],
    ['off', false],
    [true, true],
    ['true', true],
    [null, true],
]);
