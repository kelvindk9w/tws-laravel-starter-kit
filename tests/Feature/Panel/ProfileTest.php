<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Uploads\Models\Upload;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// =============================================================================
// Perfil (Livewire — Fase 6): dados, senha de login, senha de transação
// (TransactionPasswordService da Fase 3) e avatar (função global de upload
// da Fase 5). Tudo na mesma tela (ADR-005).
// =============================================================================

beforeEach(function () {
    Storage::fake('uploads-test');
    config()->set('uploads.disk', 'uploads-test');
});

it('exige autenticação (deny-by-default)', function () {
    $this->get('/profile')->assertRedirect(route('login'));
});

it('renderiza com os dados do usuário', function () {
    $user = User::factory()->create(['name' => 'João Teste', 'email' => 'joao@example.com']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertOk()
        ->assertSee('joao@example.com')
        ->assertSet('name', 'João Teste');
});

it('atualiza o nome', function () {
    $user = User::factory()->create(['name' => 'Antigo']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'Nome Novo')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Nome Novo');
});

it('troca a senha de login exigindo a senha atual', function () {
    $user = User::factory()->create(['password' => Hash::make('SenhaAtual123')]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('currentPassword', 'SenhaAtual123')
        ->set('password', 'NovaSenhaForte456')
        ->set('passwordConfirmation', 'NovaSenhaForte456')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('NovaSenhaForte456', (string) $user->fresh()->password))->toBeTrue();
});

it('rejeita troca de senha com a senha atual errada', function () {
    $user = User::factory()->create(['password' => Hash::make('SenhaAtual123')]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('currentPassword', 'Errada')
        ->set('password', 'NovaSenhaForte456')
        ->set('passwordConfirmation', 'NovaSenhaForte456')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('SenhaAtual123', (string) $user->fresh()->password))->toBeTrue();
});

it('define a senha de transação (hash separado — ADR-006)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('transactionPassword', 'TransacaoNova1')
        ->set('transactionPasswordConfirmation', 'TransacaoNova1')
        ->call('updateTransactionPassword')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->hasTransactionPassword())->toBeTrue()
        ->and(Hash::check('TransacaoNova1', (string) $user->transaction_password))->toBeTrue();
});

it('exige a senha de transação ATUAL para alterá-la', function () {
    $user = User::factory()->withTransactionPassword()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('currentTransactionPassword', 'errada')
        ->set('transactionPassword', 'OutraTransacao2')
        ->set('transactionPasswordConfirmation', 'OutraTransacao2')
        ->call('updateTransactionPassword')
        ->assertHasErrors(['currentTransactionPassword']);
});

it('rejeita senha de transação igual à senha de login', function () {
    $user = User::factory()->create(['password' => Hash::make('MesmaSenha123')]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('transactionPassword', 'MesmaSenha123')
        ->set('transactionPasswordConfirmation', 'MesmaSenha123')
        ->call('updateTransactionPassword')
        ->assertHasErrors(['transactionPassword']);

    expect($user->fresh()->hasTransactionPassword())->toBeFalse();
});

it('faz upload do avatar pela função global de upload seguro (Fase 5)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('avatar', fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png'))
        ->call('updateAvatar')
        ->assertHasNoErrors();

    $upload = Upload::query()->sole();

    expect($upload->mime)->toBe('image/png')
        ->and($upload->path)->toStartWith('avatars/')
        ->and($user->fresh()->avatar_upload_id)->toBe($upload->id);

    Storage::disk('uploads-test')->assertExists((string) $upload->path);
});

it('rejeita avatar que não é imagem de verdade (validação por conteúdo)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('avatar', fixtureArquivoLivewire(fixtureBytesElf(), 'foto.png'))
        ->call('updateAvatar')
        ->assertHasErrors(['avatar']);

    expect(Upload::query()->count())->toBe(0)
        ->and($user->fresh()->avatar_upload_id)->toBeNull();
});
