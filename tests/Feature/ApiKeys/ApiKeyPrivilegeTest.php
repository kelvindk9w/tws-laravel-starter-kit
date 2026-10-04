<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\ApiKeys\Support\Exceptions\ApiKeyPrivilegeExceededException;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;

// =============================================================================
// Sem escalada de privilégio pela API v1 (1.1.2): uma chave de API não cria,
// não rotaciona e não edita chave MAIS AMPLA que ela mesma, e `scopes`
// omitido na criação herda os escopos dela (antes: `*:*`). A regra mora no
// ApiKeyService; aqui ela é provada pela requisição de verdade e, para o
// caminho que as rotas já fecham (chave restrita a projetos), no service.
// =============================================================================

beforeEach(function () {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 100);
    // Vários tokens de ação sensível seguidos no mesmo teste.
    config()->set('auth.verification.resend_cooldown_seconds', 0);
});

/**
 * Headers da API com um token de ação sensível NOVO (uso único).
 *
 * @return array<string, string>
 */
function headersSensiveis(User $user, ApiKey $key, string $secret): array
{
    return [...headersApi($key, $secret), 'X-Sensitive-Action-Token' => tokenAcaoSensivel($user)];
}

it('chave com escopos restritos: subconjunto cria; superconjunto e *:* são 403 api_key_scope_exceeded; omitido herda', function () {
    $user = User::factory()->withTransactionPassword()->create();
    ['api_key' => $key, 'secret_key' => $secret] = criarChave($user, ['scopes' => ['api-keys:create', 'api-keys:rotate', 'orders:*']]);

    $this->postJson('/api/v1/api-keys', ['name' => 'Menor', 'scopes' => ['orders:create']], headersSensiveis($user, $key, $secret))
        ->assertCreated()
        ->assertJsonPath('data.scopes', ['orders:create']);

    foreach ([['customers:read'], ['*:*'], ['orders:*', 'api-keys:revoke'], ['*:create']] as $scopes) {
        assertErroApi(
            $this->postJson('/api/v1/api-keys', ['name' => 'Maior', 'scopes' => $scopes], headersSensiveis($user, $key, $secret)),
            403,
            ApiKeyPrivilegeExceededException::SCOPE,
        )->assertJsonMissingPath('secret_key');
    }

    // `scopes` omitido: a nova sai com os MESMOS escopos da chave que chamou.
    $this->postJson('/api/v1/api-keys', ['name' => 'Herdada'], headersSensiveis($user, $key, $secret))
        ->assertCreated()
        ->assertJsonPath('data.scopes', ['api-keys:create', 'api-keys:rotate', 'orders:*']);

    expect(ApiKey::query()->count())->toBe(3)
        ->and(ApiKey::all()->filter(fn (ApiKey $k): bool => in_array('*:*', (array) $k->scopes, true))->count())->toBe(0);
});

it('chave restrita não rotaciona chave mais ampla (nem recebe a secreta nova dela)', function () {
    $user = User::factory()->withTransactionPassword()->create();
    ['api_key' => $key, 'secret_key' => $secret] = criarChave($user, ['scopes' => ['api-keys:rotate', 'orders:*']]);
    ['api_key' => $ampla] = criarChave($user, ['scopes' => ['*:*']]);
    ['api_key' => $menor] = criarChave($user, ['scopes' => ['orders:read']]);

    assertErroApi(
        $this->postJson("/api/v1/api-keys/{$ampla->uuid}/rotate", [], headersSensiveis($user, $key, $secret)),
        403,
        ApiKeyPrivilegeExceededException::SCOPE,
    )->assertJsonMissingPath('secret_key');

    expect($ampla->refresh()->rotated_to_id)->toBeNull();

    // Chave que cabe nela: rotaciona normalmente.
    $this->postJson("/api/v1/api-keys/{$menor->uuid}/rotate", [], headersSensiveis($user, $key, $secret))
        ->assertCreated()
        ->assertJsonPath('data.scopes', ['orders:read']);

    // A si mesma: sempre.
    $this->postJson("/api/v1/api-keys/{$key->uuid}/rotate", [], headersSensiveis($user, $key, $secret))
        ->assertCreated();
});

it('chave restrita não edita os projetos de chave mais ampla', function () {
    $user = User::factory()->create();
    $loja = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);
    ['api_key' => $key, 'secret_key' => $secret] = criarChave($user, ['scopes' => ['api-keys:assign', 'orders:*']]);
    ['api_key' => $ampla] = criarChave($user, ['scopes' => ['*:*'], 'project_uuids' => [$loja->uuid]]);
    ['api_key' => $menor] = criarChave($user, ['scopes' => ['orders:read'], 'project_uuids' => [$loja->uuid]]);

    // Esvaziar a lista tornaria a chave `*:*` válida para a conta toda.
    assertErroApi(
        $this->putJson("/api/v1/api-keys/{$ampla->uuid}/projects", ['project_uuids' => []], headersApi($key, $secret)),
        403,
        ApiKeyPrivilegeExceededException::SCOPE,
    );

    expect($ampla->refresh()->isRestrictedToProjects())->toBeTrue()
        ->and($ampla->projects()->pluck('projects.id')->all())->toBe([$loja->id]);

    $this->putJson("/api/v1/api-keys/{$menor->uuid}/projects", ['project_uuids' => []], headersApi($key, $secret))
        ->assertOk();

    expect($menor->refresh()->isRestrictedToProjects())->toBeFalse();
});

it('chave *:* segue podendo tudo: cria *:* explícito e omitido sai *:*', function () {
    $user = User::factory()->withTransactionPassword()->create();
    ['api_key' => $key, 'secret_key' => $secret] = criarChave($user);

    $this->postJson('/api/v1/api-keys', ['name' => 'Omitido'], headersSensiveis($user, $key, $secret))
        ->assertCreated()
        ->assertJsonPath('data.scopes', ['*:*']);

    $this->postJson('/api/v1/api-keys', ['name' => 'Explícito', 'scopes' => ['*:*']], headersSensiveis($user, $key, $secret))
        ->assertCreated()
        ->assertJsonPath('data.scopes', ['*:*']);
});

it('no service, chave autenticada restrita a projetos: a nova herda os projetos dela e não sai deles', function () {
    // As rotas de criação e de vínculo exigem chave de conta (account.key),
    // então pela requisição este caminho já é 403. A regra do service é a
    // defesa em profundidade para quem o chamar direto com a chave no contexto.
    $user = User::factory()->create();
    $lojaA = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);
    $lojaB = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja B']);
    ['api_key' => $restrita] = criarChave($user, ['scopes' => ['*:*'], 'project_uuids' => [$lojaA->uuid]]);
    ['api_key' => $outra] = criarChave($user, ['scopes' => ['orders:read'], 'project_uuids' => [$lojaA->uuid]]);

    app(TenantContext::class)->resolve($user, $restrita);
    $service = app(ApiKeyService::class);

    $herdada = $service->create($user, ['name' => 'Sem projetos no pedido'])['api_key'];

    expect($herdada->isRestrictedToProjects())->toBeTrue()
        ->and($herdada->projects()->pluck('projects.id')->all())->toBe([$lojaA->id]);

    expect(fn () => $service->create($user, ['name' => 'Fora', 'project_uuids' => [$lojaB->uuid]]))
        ->toThrow(ApiKeyPrivilegeExceededException::class);

    expect(fn () => $service->syncProjects($outra, []))
        ->toThrow(ApiKeyPrivilegeExceededException::class);

    expect($outra->refresh()->isRestrictedToProjects())->toBeTrue();
});

it('pelo painel (sem chave no contexto) nada muda: omitido continua *:*', function () {
    $user = User::factory()->create();

    expect(criarChave($user)['api_key']->scopes)->toBe(['*:*']);
});
