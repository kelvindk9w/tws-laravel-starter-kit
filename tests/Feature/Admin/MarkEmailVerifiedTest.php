<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Support\MarkEmailVerifiedAction;
use App\Filament\Resources\Users\Support\UserAdminGuard;
use App\Filament\Support\AdminAuditTrail;
use App\Filament\Support\CardActions;
use App\Filament\Support\ViewModeToggle;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

// =============================================================================
// Ação de suporte "Marcar e-mail como verificado" no /admin (listagem, cards e
// detalhe do usuário) e o filtro "E-mail verificado" da listagem.
//
// Regras: só aparece para conta NÃO verificada; conta demo fica de fora (some
// e é recusada no servidor); pede confirmação; dispara `Verified`; registra a
// linha `admin.action` na trilha (canal request_log) com a correlação da
// requisição, sem dado pessoal.
// =============================================================================

beforeEach(function () {
    config()->set('ui.demo_login.enabled', true);

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

/**
 * Captura as linhas do canal request_log (o resto do Log segue funcionando).
 *
 * @return ArrayObject<int, array{level: string, message: string, context: array<string, mixed>}>
 */
function captureRequestLogChannel(): ArrayObject
{
    $linhas = new ArrayObject;

    $logger = Mockery::mock();
    $logger->shouldIgnoreMissing();

    foreach (['info', 'notice', 'warning'] as $nivel) {
        $logger->shouldReceive($nivel)->andReturnUsing(function (string $mensagem, array $contexto = []) use ($linhas, $nivel): void {
            $linhas[] = ['level' => $nivel, 'message' => $mensagem, 'context' => $contexto];
        });
    }

    Log::shouldReceive('channel')->with('request_log')->andReturn($logger);
    Log::getFacadeRoot()->shouldIgnoreMissing();

    return $linhas;
}

// -----------------------------------------------------------------------------
// Visibilidade
// -----------------------------------------------------------------------------

it('aparece na listagem só para conta com e-mail não verificado', function () {
    $pendente = User::factory()->unverified()->create();
    $verificado = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible(MarkEmailVerifiedAction::NAME, $pendente)
        ->assertTableActionHidden(MarkEmailVerifiedAction::NAME, $verificado);
});

it('aparece no detalhe só para conta com e-mail não verificado', function () {
    $pendente = User::factory()->unverified()->create();
    $verificado = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $pendente->uuid])
        ->assertActionVisible(MarkEmailVerifiedAction::NAME);

    Livewire::test(ViewUser::class, ['record' => $verificado->uuid])
        ->assertActionHidden(MarkEmailVerifiedAction::NAME);
});

it('não aparece para conta demo, nem com a coluna vazia e o modo demo desligado', function () {
    // Modo demo LIGADO: a conta demo já conta como verificada (blindagem).
    $demo = User::factory()->unverified()->create(['email' => config('ui.demo_login.email')]);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden(MarkEmailVerifiedAction::NAME, $demo);

    // Modo demo DESLIGADO: hasVerifiedEmail passa a olhar só a coluna, e é a
    // guarda do admin (UserAdminGuard) que mantém a ação fora da conta demo.
    config()->set('ui.demo_login.enabled', false);

    expect($demo->fresh()->hasVerifiedEmail())->toBeFalse();

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden(MarkEmailVerifiedAction::NAME, $demo);

    Livewire::test(ViewUser::class, ['record' => $demo->uuid])
        ->assertActionHidden(MarkEmailVerifiedAction::NAME);

    expect($demo->fresh()->email_verified_at)->toBeNull();
});

it('só o super admin chega à ação: conta sem a flag recebe 403 no detalhe', function () {
    $pendente = User::factory()->unverified()->create();
    $comum = User::factory()->create(['is_admin' => false]);

    $this->actingAs($comum)
        ->get(route('filament.admin.resources.users.view', ['record' => $pendente->uuid]))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.users.view', ['record' => $pendente->uuid]))
        ->assertOk()
        ->assertSee(__('admin.users.mark_email_verified'));
});

it('a guarda recusa conta demo e libera conta comum', function () {
    $demo = User::factory()->unverified()->create(['email' => config('ui.demo_admin.email')]);
    $comum = User::factory()->unverified()->create();

    expect(UserAdminGuard::verifyEmailDenial($demo))->toBe(__('admin.users.demo_protected'))
        ->and(UserAdminGuard::verifyEmailDenial($comum))->toBeNull()
        ->and(MarkEmailVerifiedAction::isAvailableFor($demo))->toBeFalse()
        ->and(MarkEmailVerifiedAction::isAvailableFor($comum))->toBeTrue();
});

it('pede confirmação antes de marcar', function () {
    $pendente = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->assertActionExists(
            TestAction::make(MarkEmailVerifiedAction::NAME)->table($pendente),
            fn ($action): bool => $action->isConfirmationRequired(),
        );
});

// -----------------------------------------------------------------------------
// Execução
// -----------------------------------------------------------------------------

it('marca o e-mail como verificado pela listagem e dispara Verified', function () {
    Event::fake([Verified::class]);

    $pendente = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction(MarkEmailVerifiedAction::NAME, $pendente)
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('admin.users.email_marked_verified'));

    expect($pendente->fresh()->email_verified_at)->not->toBeNull()
        ->and($pendente->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($pendente));
});

it('marca o e-mail como verificado pelo detalhe e a ação some em seguida', function () {
    $pendente = User::factory()->unverified()->create();

    Livewire::test(ViewUser::class, ['record' => $pendente->uuid])
        ->callAction(MarkEmailVerifiedAction::NAME)
        ->assertNotified(__('admin.users.email_marked_verified'));

    expect($pendente->fresh()->email_verified_at)->not->toBeNull();

    Livewire::test(ViewUser::class, ['record' => $pendente->uuid])
        ->assertActionHidden(MarkEmailVerifiedAction::NAME);
});

it('registra a ação na trilha como ação de admin, sem dado pessoal', function () {
    $linhas = captureRequestLogChannel();

    $pendente = User::factory()->unverified()->create(['email' => 'suporte-alvo@example.com']);

    Livewire::test(ListUsers::class)
        ->callTableAction(MarkEmailVerifiedAction::NAME, $pendente);

    $registro = collect($linhas)->firstWhere('message', AdminAuditTrail::MESSAGE);

    expect($registro)->not->toBeNull()
        ->and($registro['level'])->toBe('notice')
        ->and($registro['context'])->toMatchArray([
            'action' => MarkEmailVerifiedAction::AUDIT_ACTION,
            'actor_uuid' => $this->admin->uuid,
            'target_type' => 'User',
            'target_uuid' => $pendente->uuid,
        ])
        ->and($registro['context']['correlation_id'])->toBeString()->not->toBeEmpty()
        ->and(json_encode($registro))->not->toContain('suporte-alvo@example.com');
});

it('confirmação que chega depois de outro admin já ter verificado não regrava nem registra', function () {
    $linhas = captureRequestLogChannel();

    $pendente = User::factory()->unverified()->create();

    // O modal abre com a conta ainda pendente...
    $pagina = Livewire::test(ListUsers::class)
        ->mountTableAction(MarkEmailVerifiedAction::NAME, $pendente);

    // ...e, antes da confirmação, outra aba/outro admin confirma o e-mail. O
    // Filament reavalia visible() no SERVIDOR ao executar: a ação não roda.
    $verificadoEm = now()->subMonth()->startOfSecond();
    $pendente->forceFill(['email_verified_at' => $verificadoEm])->save();

    $pagina->callMountedTableAction();

    expect($pendente->fresh()->email_verified_at->equalTo($verificadoEm))->toBeTrue()
        ->and(collect($linhas)->where('message', AdminAuditTrail::MESSAGE))->toBeEmpty();
});

it('a execução reconfere a guarda no servidor, mesmo chamada fora da tela', function () {
    // Segunda barreira: se a ação for reaproveitada com outra regra de
    // visibilidade, a própria execução ainda recusa conta demo.
    config()->set('ui.demo_login.enabled', false);
    $linhas = captureRequestLogChannel();

    $demo = User::factory()->unverified()->create(['email' => config('ui.demo_login.email')]);

    MarkEmailVerifiedAction::make()->record($demo)->call();

    expect($demo->fresh()->email_verified_at)->toBeNull()
        ->and(collect($linhas)->where('message', AdminAuditTrail::MESSAGE))->toBeEmpty();
});

it('a execução não regrava a data de quem já está verificado, mesmo chamada fora da tela', function () {
    $linhas = captureRequestLogChannel();

    $verificadoEm = now()->subMonth()->startOfSecond();
    $verificado = User::factory()->create(['email_verified_at' => $verificadoEm]);

    MarkEmailVerifiedAction::make()->record($verificado)->call();

    expect($verificado->fresh()->email_verified_at->equalTo($verificadoEm))->toBeTrue()
        ->and(collect($linhas)->where('message', AdminAuditTrail::MESSAGE))->toBeEmpty();
});

// -----------------------------------------------------------------------------
// Modo cards: ícone colorido com tooltip
// -----------------------------------------------------------------------------

it('no modo cards vira ícone verde com o nome no hover', function () {
    $pendente = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)->callTableAction(ViewModeToggle::NAME);

    $acao = collect(Livewire::test(ListUsers::class)->instance()->getTable()->getFlatRecordActions())
        ->get(MarkEmailVerifiedAction::NAME);

    expect($acao)->not->toBeNull()
        ->and($acao->isIconButton())->toBeTrue()
        ->and($acao->getColor())->toBe('success')
        ->and($acao->getTooltip())->toBe(__('admin.users.mark_email_verified'))
        ->and(CardActions::colorFor(MarkEmailVerifiedAction::NAME))->toBe('success');

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible(MarkEmailVerifiedAction::NAME, $pendente);
});

// -----------------------------------------------------------------------------
// Filtro "E-mail verificado"
// -----------------------------------------------------------------------------

it('filtra a listagem por e-mail verificado: sim e não', function () {
    $pendente = User::factory()->unverified()->create();
    $verificado = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', true)
        ->assertCanSeeTableRecords([$verificado])
        ->assertCanNotSeeTableRecords([$pendente]);

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$pendente])
        ->assertCanNotSeeTableRecords([$verificado]);
});

// -----------------------------------------------------------------------------
// Tradução
// -----------------------------------------------------------------------------

it('tem as mensagens da ação nos três idiomas', function (string $locale) {
    $chaves = [
        'email_verified', 'mark_email_verified', 'mark_email_verified_heading',
        'mark_email_verified_warning', 'mark_email_verified_confirm',
        'email_marked_verified', 'email_already_verified',
    ];

    foreach ($chaves as $chave) {
        // hasForLocale ignora o idioma de fallback: chave faltando no es não
        // passa só porque existe no pt_BR.
        expect(app('translator')->hasForLocale("admin.users.{$chave}", $locale))
            ->toBeTrue("Falta admin.users.{$chave} em {$locale}");
    }
})->with(['pt_BR', 'en', 'es']);
