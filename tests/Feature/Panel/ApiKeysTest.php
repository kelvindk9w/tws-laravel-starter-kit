<?php

declare(strict_types=1);

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\ApiKeys\Support\ApiKeyHasher;
use App\Core\Auth\Mail\VerificationCodeMail;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use App\Livewire\ApiKeys\Index;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// =============================================================================
// Chaves de API pela UI (Livewire — Fase 6, ADR-006): a tela mais importante.
// Fluxo completo com ação sensível REAL (senha de transação + código por
// e-mail — o código é capturado do mailable via Mail::fake) consumindo o
// ApiKeyService/SensitiveActionService das Fases 3/4 — nada duplicado.
// =============================================================================

/**
 * Código de verificação mais recente "enviado" (capturado do mailable fake).
 */
function latestSentCode(): string
{
    /** @var VerificationCodeMail $mail */
    $mail = Mail::queued(VerificationCodeMail::class)->last();

    return $mail->code;
}

beforeEach(function () {
    Mail::fake();
    // Sem cooldown de reenvio nos testes (a regra em si é coberta na Fase 3).
    config()->set('auth.verification.resend_cooldown_seconds', 0);
});

it('exige autenticação (deny-by-default)', function () {
    $this->get('/api-keys')->assertRedirect(route('login'));
});

it('lista apenas as chaves do próprio usuário com status e último uso', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();

    app(ApiKeyService::class)->create($user, ['name' => 'Minha Integração']);
    app(ApiKeyService::class)->create($outro, ['name' => 'Chave Alheia']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertOk()
        ->assertSee('Minha Integração')
        ->assertSee(__('panel.api_keys.status_active'))
        ->assertSee(__('panel.common.never'))
        ->assertDontSee('Chave Alheia');
});

it('cria chave pela UI com 2FA completo e exibe a secreta UMA única vez', function () {
    $user = User::factory()->withTransactionPassword()->create();
    $projeto = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);

    $component = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Integração ERP')
        ->set('selectedProjectUuids', [$projeto->uuid])
        ->call('requestCreate')
        ->assertHasNoErrors()
        ->assertSet('pendingAction', 'create')
        // Passo 1: senha de transação → código por e-mail.
        ->set('transactionPassword', 'Trans4cao!Segura')
        ->call('sendSensitiveCode')
        ->assertSet('codeSent', true);

    Mail::assertQueued(VerificationCodeMail::class);

    // Passo 2: código → token de uso único consumido → chave criada.
    $component
        ->set('verificationCode', latestSentCode())
        ->call('confirmSensitiveAction')
        ->assertHasNoErrors()
        ->assertSet('pendingAction', null);

    $secret = $component->get('revealedSecretKey');
    $public = $component->get('revealedPublicKey');

    expect($public)->toStartWith('pk_')
        ->and($secret)->toStartWith('sk_');

    $key = ApiKey::query()->sole();

    expect($key->name)->toBe('Integração ERP')
        ->and($key->public_key)->toBe($public)
        ->and($key->scopes)->toBe(['*:*']) // padrão: tudo habilitado (ADR-006)
        ->and($key->projects->pluck('id')->all())->toBe([$projeto->id])
        // Só o HASH no banco — a secreta em claro nunca toca o banco.
        ->and(app(ApiKeyHasher::class)->verify($secret, (string) $key->secret_hash))->toBeTrue();

    // "Já guardei" → a secreta some e nunca mais é exibida (ADR-006).
    $component->call('dismissSecret')
        ->assertSet('revealedSecretKey', null);
});

it('cria chave com escopos granulares quando o toggle "todas" está desligado', function () {
    $user = User::factory()->withTransactionPassword()->create();

    $component = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Só leitura de projetos')
        ->set('allScopes', false)
        ->set('selectedScopes', ['projects:read', 'uploads:create'])
        ->call('requestCreate')
        ->set('transactionPassword', 'Trans4cao!Segura')
        ->call('sendSensitiveCode')
        ->set('verificationCode', latestSentCode())
        ->call('confirmSensitiveAction')
        ->assertHasNoErrors();

    $key = ApiKey::query()->sole();

    expect($key->scopes)->toBe(['projects:read', 'uploads:create'])
        ->and($key->allows('projects:read'))->toBeTrue()
        ->and($key->allows('projects:delete'))->toBeFalse();
});

it('exige seleção granular quando o toggle "todas" está desligado', function () {
    $user = User::factory()->withTransactionPassword()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Sem escopo')
        ->set('allScopes', false)
        ->call('requestCreate')
        ->assertHasErrors(['selectedScopes']);

    expect(ApiKey::query()->count())->toBe(0);
});

it('bloqueia a criação quando a senha de transação não foi definida', function () {
    $user = User::factory()->create(); // sem senha de transação

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Qualquer')
        ->call('requestCreate')
        ->assertHasErrors(['name']);

    expect(ApiKey::query()->count())->toBe(0);
});

it('rejeita senha de transação incorreta no fluxo sensível', function () {
    $user = User::factory()->withTransactionPassword()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'ERP')
        ->call('requestCreate')
        ->set('transactionPassword', 'senha-errada')
        ->call('sendSensitiveCode')
        ->assertHasErrors(['transactionPassword'])
        ->assertSet('codeSent', false);

    expect(ApiKey::query()->count())->toBe(0);
});

it('rejeita código de verificação incorreto', function () {
    $user = User::factory()->withTransactionPassword()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'ERP')
        ->call('requestCreate')
        ->set('transactionPassword', 'Trans4cao!Segura')
        ->call('sendSensitiveCode')
        ->set('verificationCode', '000000')
        ->call('confirmSensitiveAction')
        ->assertHasErrors(['verificationCode']);

    expect(ApiKey::query()->count())->toBe(0);
});

it('rotaciona com grace period: nova chave herda tudo e a antiga fica em transição', function () {
    $user = User::factory()->withTransactionPassword()->create();
    $projeto = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja']);

    $original = app(ApiKeyService::class)->create($user, [
        'name' => 'Principal',
        'project_uuids' => [$projeto->uuid],
    ])['api_key'];

    $component = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startRotate', $original->uuid)
        ->set('gracePeriodMinutes', 60)
        ->call('requestRotate')
        ->set('transactionPassword', 'Trans4cao!Segura')
        ->call('sendSensitiveCode')
        ->set('verificationCode', latestSentCode())
        ->call('confirmSensitiveAction')
        ->assertHasNoErrors();

    $novaSecreta = $component->get('revealedSecretKey');

    expect($novaSecreta)->toStartWith('sk_')
        ->and(ApiKey::query()->count())->toBe(2);

    $nova = ApiKey::query()->where('id', '!=', $original->id)->sole();
    $antiga = $original->fresh();

    expect($nova->name)->toBe('Principal')
        ->and($nova->rotated_from_id)->toBe($original->id)
        ->and($nova->projects->pluck('id')->all())->toBe([$projeto->id])
        // Grace de 60 min: antiga segue utilizável até grace_ends_at.
        ->and($antiga->grace_ends_at)->not->toBeNull()
        ->and($antiga->isUsable())->toBeTrue();
});

it('revoga com confirmação na mesma tela (irreversível)', function () {
    $user = User::factory()->create();
    $key = app(ApiKeyService::class)->create($user, ['name' => 'Vai morrer'])['api_key'];

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startRevoke', $key->uuid)
        ->assertSet('revokingKeyUuid', $key->uuid)
        ->call('revoke');

    expect($key->fresh()->status)->toBe(ApiKeyStatus::Revoked);
});

it('edita os vínculos N:N chave ↔ projetos pela UI', function () {
    $user = User::factory()->create();
    $projeto = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja B']);
    $key = app(ApiKeyService::class)->create($user, ['name' => 'Chave'])['api_key'];

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startEditProjects', $key->uuid)
        ->set('editingProjectsSelection', [$projeto->uuid])
        ->call('saveProjects')
        ->assertHasNoErrors();

    expect($key->fresh()->projects->pluck('id')->all())->toBe([$projeto->id]);
});

it('não vincula projeto de outro tenant a uma chave (anti-IDOR)', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();
    $alheio = Project::createWithPublicCodeRetry(['user_id' => $outro->id, 'name' => 'Alheio']);
    $key = app(ApiKeyService::class)->create($user, ['name' => 'Chave'])['api_key'];

    // resolveProjectIds (Fase 4) rejeita uuid alheio → erro de validação,
    // e o vínculo NÃO é criado.
    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startEditProjects', $key->uuid)
        ->set('editingProjectsSelection', [$alheio->uuid])
        ->call('saveProjects')
        ->assertHasErrors(['editingProjectsSelection']);

    expect($key->fresh()->projects)->toHaveCount(0);
});

it('não rotaciona nem revoga chave de outro tenant (404 uniforme)', function () {
    $user = User::factory()->withTransactionPassword()->create();
    $outro = User::factory()->create();
    $alheia = app(ApiKeyService::class)->create($outro, ['name' => 'Alheia'])['api_key'];

    // firstOrFail → ModelNotFoundException (404 na request real do Livewire).
    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startRotate', $alheia->uuid);
})->throws(ModelNotFoundException::class);

it('não revoga chave de outro tenant (404 uniforme)', function () {
    $user = User::factory()->withTransactionPassword()->create();
    $outro = User::factory()->create();
    $alheia = app(ApiKeyService::class)->create($outro, ['name' => 'Alheia'])['api_key'];

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startRevoke', $alheia->uuid);
})->throws(ModelNotFoundException::class);
