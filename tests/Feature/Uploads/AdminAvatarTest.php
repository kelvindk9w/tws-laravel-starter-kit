<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Uploads\Models\Upload;
use App\Filament\Pages\Profile as AdminProfile;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// =============================================================================
// FOTO DE PERFIL NO /admin (ponta a ponta).
//
// O buraco que estes testes fecham: o super admin cadastrava usuário sem
// foto nenhuma e o próprio admin não tinha onde trocar a dele — a foto só
// existia no painel do cliente. Pior: o caminho óbvio de resolver isso
// (FileUpload do Filament) gravaria o arquivo direto no disco, PULANDO a
// função global de upload do kit e, com ela, a validação por conteúdo.
//
// Por isso cada teste aqui prova as duas coisas ao mesmo tempo: a foto
// funciona E ela passou pelo caminho seguro (registro em `uploads`, nome
// derivado do MIME real, URL assinada, arquivo malicioso recusado).
// =============================================================================

beforeEach(function () {
    Storage::fake('uploads-test');
    config()->set('uploads.disk', 'uploads-test');

    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Operadora Admin']);
    $this->actingAs($this->admin);
});

// -----------------------------------------------------------------------------
// Cadastro de usuário com foto
// -----------------------------------------------------------------------------

it('cria usuário com foto: o arquivo vira Upload registrado e vinculado', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Pessoa Com Foto',
            'email' => 'com-foto@example.com',
            'password' => 'Senha-do-admin-1',
            'password_confirmation' => 'Senha-do-admin-1',
            'status' => 'active',
            'avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'retrato.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'com-foto@example.com')->sole();
    $upload = Upload::query()->sole();

    expect($user->avatar_upload_id)->toBe($upload->id)
        ->and($upload->mime)->toBe('image/png')
        ->and($upload->path)->toStartWith('avatars/')
        // Nome do arquivo derivado do MIME real, nunca do nome original.
        ->and(basename((string) $upload->path))->toMatch('/^[0-9a-f-]{36}\.png$/')
        ->and($upload->original_name)->toBe('retrato.png');

    Storage::disk('uploads-test')->assertExists((string) $upload->path);
});

it('cria usuário sem foto normalmente (a foto é opcional)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Pessoa Sem Foto',
            'email' => 'sem-foto@example.com',
            'password' => 'Senha-do-admin-1',
            'password_confirmation' => 'Senha-do-admin-1',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'sem-foto@example.com')->sole();

    expect($user->avatar_upload_id)->toBeNull()
        ->and(Upload::query()->count())->toBe(0);
});

it('recusa arquivo que não é imagem de verdade (.txt renomeado para .png)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Pessoa Maliciosa',
            'email' => 'malicioso@example.com',
            'password' => 'Senha-do-admin-1',
            'password_confirmation' => 'Senha-do-admin-1',
            'status' => 'active',
            'avatar' => fixtureArquivoLivewire('isto aqui e texto puro, nao uma imagem', 'retrato.png'),
        ])
        ->call('create')
        ->assertHasFormErrors(['avatar']);

    expect(Upload::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'malicioso@example.com')->exists())->toBeFalse();
});

it('recusa executável disfarçado de imagem (magic bytes, não extensão)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Pessoa Maliciosa',
            'email' => 'elf@example.com',
            'password' => 'Senha-do-admin-1',
            'password_confirmation' => 'Senha-do-admin-1',
            'status' => 'active',
            'avatar' => fixtureArquivoLivewire(fixtureBytesElf(), 'retrato.png'),
        ])
        ->call('create')
        ->assertHasFormErrors(['avatar']);

    expect(Upload::query()->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// Troca e remoção
// -----------------------------------------------------------------------------

it('troca a foto de um usuário já cadastrado', function () {
    $user = User::factory()->create(['email' => 'troca@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'primeira.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $primeira = $user->fresh()->avatar_upload_id;
    expect($primeira)->not->toBeNull();

    // O campo é de UM arquivo: no navegador, escolher outro SUBSTITUI o
    // item. O harness do Livewire empilha (ele sempre faz merge no upload),
    // então limpamos antes — é isso que reproduz a tela.
    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->set('data.avatar', [])
        ->set('data.avatar', [fixtureArquivoLivewire(fixtureBytesPng(), 'segunda.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $segunda = $user->fresh()->avatar_upload_id;

    expect($segunda)->not->toBeNull()->not->toBe($primeira)
        ->and(Upload::query()->count())->toBe(2);
});

it('remove a foto deixando o campo vazio: volta para as iniciais', function () {
    $user = User::factory()->create(['email' => 'remove@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png')])
        ->call('save');

    expect($user->fresh()->avatar_upload_id)->not->toBeNull();

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->set('data.avatar', [])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->avatar_upload_id)->toBeNull()
        ->and(Filament::getUserAvatarUrl($user->fresh()))->toStartWith('data:image/svg+xml');
});

it('abrir a edição sem mexer na foto NÃO apaga a foto que já está lá', function () {
    $user = User::factory()->create(['email' => 'mantem@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png')])
        ->call('save');

    $vinculo = $user->fresh()->avatar_upload_id;

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['name' => 'Só troquei o nome'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->avatar_upload_id)->toBe($vinculo);
});

// -----------------------------------------------------------------------------
// A foto aparece: listagem, detalhe e cabeçalho
// -----------------------------------------------------------------------------

it('a foto aparece na listagem e no detalhe do usuário', function () {
    $user = User::factory()->create(['email' => 'aparece@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png')])
        ->call('save');

    $caminho = $user->fresh()->avatar->path;

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$user])
        ->assertSee(basename((string) $caminho));

    Livewire::test(ViewUser::class, ['record' => $user->uuid])
        ->assertOk()
        ->assertSee(basename((string) $caminho));
});

it('quem não tem foto mostra as iniciais, nunca um quadrado quebrado', function () {
    $user = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);

    expect(Filament::getUserAvatarUrl($user))->toStartWith('data:image/svg+xml');

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$user]);
});

// -----------------------------------------------------------------------------
// O próprio admin troca a sua foto em /admin/profile
// -----------------------------------------------------------------------------

it('o admin troca a própria foto no perfil do painel', function () {
    Livewire::test(AdminProfile::class)
        ->fillForm([
            'name' => 'Operadora Admin',
            'avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'minha-foto.png'),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('admin.profile.saved'));

    $upload = Upload::query()->sole();

    expect($this->admin->fresh()->avatar_upload_id)->toBe($upload->id)
        // O avatar do CABEÇALHO passa a ser a foto (mesmo vínculo).
        ->and(Filament::getUserAvatarUrl($this->admin->fresh()))->not->toStartWith('data:');
});

it('o perfil do admin recusa arquivo inválido com erro no campo', function () {
    Livewire::test(AdminProfile::class)
        ->fillForm([
            'name' => 'Operadora Admin',
            'avatar' => fixtureArquivoLivewire(fixtureBytesElf(), 'minha-foto.png'),
        ])
        ->call('save')
        ->assertHasFormErrors(['avatar']);

    expect($this->admin->fresh()->avatar_upload_id)->toBeNull();
});

it('o perfil do admin abre já mostrando a foto atual', function () {
    Livewire::test(AdminProfile::class)
        ->fillForm(['name' => 'Operadora Admin', 'avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png')])
        ->call('save');

    $caminho = $this->admin->fresh()->avatar->path;

    $estado = Livewire::test(AdminProfile::class)->assertOk()->get('data.avatar');

    expect(array_values((array) $estado))->toContain($caminho);
});

// -----------------------------------------------------------------------------
// Política de entrega: nunca uma URL pública e eterna
// -----------------------------------------------------------------------------

it('a foto é servida por URL temporária, nunca por caminho público fixo', function () {
    $user = User::factory()->create(['email' => 'url@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->uuid])
        ->fillForm(['avatar' => fixtureArquivoLivewire(fixtureBytesPng(), 'foto.png')])
        ->call('save');

    $url = $user->fresh()->avatarUrl();

    expect($url)->toBeString()
        // O disco de teste assina com `expiration`; o disco local do kit
        // (serve => true) e o R2 assinam com `signature`/`X-Amz-*`. O que
        // se prova aqui é a POLÍTICA: a URL expira.
        ->and($url)->toMatch('/(expiration|signature|X-Amz-Signature)=/i');
});
