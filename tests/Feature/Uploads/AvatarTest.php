<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Uploads\Models\Upload;
use Illuminate\Support\Facades\Storage;

// =============================================================================
// Avatar do perfil (web autenticada — Fase 5): prova o reuso da MESMA função
// global de upload fora da API. Aqui o vínculo é o user_id da sessão e o
// destino é restrito a imagens (re-encode GD obrigatório).
// =============================================================================

beforeEach(function () {
    Storage::fake('uploads-test');
    config()->set('uploads.disk', 'uploads-test');
});

it('atualiza o avatar com imagem legítima, vinculada ao usuário da sessão', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/settings/avatar', [
        'avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'minha-foto.png'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', __('uploads.avatar_updated'))
        ->assertJsonPath('data.mime', 'image/png')
        ->assertJsonStructure(['data' => ['uuid', 'codigo_publico', 'path', 'url', 'sha256']]);

    $upload = Upload::query()->sole();
    expect($upload->user_id)->toBe($user->id)
        ->and($upload->tenant_uuid)->toBeNull()
        ->and($upload->path)->toStartWith('avatars/')
        ->and(basename((string) $upload->path))->toMatch('/^[0-9a-f-]{36}\.png$/');

    Storage::disk('uploads-test')->assertExists((string) $upload->path);
});

it('rejeita PDF no avatar (endpoint restrito a imagens)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/settings/avatar', [
        'avatar' => fixtureArquivoEnviado(fixtureBytesPdf(), 'doc.png'),
    ])->assertUnprocessable();

    expect(Upload::query()->count())->toBe(0);
});

it('exige autenticação (deny-by-default)', function () {
    $this->postJson('/settings/avatar', [
        'avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'foto.png'),
    ])->assertUnauthorized();
});
