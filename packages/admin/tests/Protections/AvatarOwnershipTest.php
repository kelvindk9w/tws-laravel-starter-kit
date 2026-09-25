<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Twstec\Kit\Admin\Pages\Profile;
use Twstec\Kit\Admin\Resources\Users\Pages\CreateUser;
use Twstec\Kit\Admin\Resources\Users\Pages\EditUser;
use Twstec\Kit\Admin\Support\AvatarUpload;
use Twstec\Kit\Admin\Tests\Fixtures\User;
use Twstec\Kit\Foundation\Audit\Enums\AuditOutcome;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Uploads\Models\Upload;

// =============================================================================
// FOTO DE PERFIL NO PAINEL: só vira foto de uma conta o upload DELA (enviado
// por ela na web, pela chave de API dela, ou a foto atual) ou o que acabou de
// ser enviado NESTE formulário. O valor do campo chega do navegador (estado do
// Livewire) e pode ser trocado por qualquer uuid: apontar para o upload de
// OUTRA pessoa é recusado ANTES de gravar qualquer coisa, e a recusa fica na
// trilha (`denied`).
//
// Correção de segurança da 2.0 — até a 1.x o vínculo não conferia o dono.
// =============================================================================

beforeEach(function (): void {
    Storage::fake('local');

    $this->operador = $this->admin(['email' => 'operador@example.com']);
    $this->actingAs($this->operador);
});

/**
 * Upload gravado no banco com o dono dado (como a função global de upload o
 * grava: `user_id` na web, `tenant_uuid` na API).
 *
 * @param  array<string, mixed>  $dono
 */
function uploadDe(array $dono): Upload
{
    $upload = new Upload;
    $upload->forceFill([
        'disk' => 'local',
        'path' => 'avatars/'.Str::uuid().'.png',
        'original_name' => 'foto.png',
        'mime' => 'image/png',
        'size' => 68,
        'sha256' => str_repeat('a', 64),
        'status' => 'stored',
        ...$dono,
    ])->save();

    return $upload;
}

function pngDeTeste(string $nome = 'foto.png'): UploadedFile
{
    $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);

    return UploadedFile::fake()->createWithContent($nome, $png);
}

it('EDITAR: apontar a foto para o upload de OUTRA pessoa é recusado, nada muda e a recusa fica na trilha', function (): void {
    $alvo = User::fixture(['name' => 'Alvo Original']);
    $outra = User::fixture();
    $alheio = uploadDe(['user_id' => $outra->id]);

    Livewire::test(EditUser::class, ['record' => $alvo->uuid])
        ->set('data.name', 'Alvo Alterado')
        ->set('data.avatar', ['forjado' => $alheio->uuid])
        ->call('save')
        ->assertNotified(__('admin.users.action_denied'));

    $evento = AuditEvent::query()->where('action', 'user.updated')->where('subject_uuid', $alvo->uuid)->sole();

    expect($alvo->fresh()->avatar_upload_id)->toBeNull()
        ->and($alvo->fresh()->name)->toBe('Alvo Original')
        ->and($evento->outcome)->toBe(AuditOutcome::Denied)
        ->and($evento->reason)->toBe(__('admin.users.avatar_not_owned'))
        ->and($evento->actor_uuid)->toBe($this->operador->uuid);
});

it('EDITAR: upload enviado pela chave de API de OUTRA pessoa também é recusado', function (): void {
    $alvo = User::fixture();
    $alheio = uploadDe(['tenant_uuid' => User::fixture()->uuid]);

    Livewire::test(EditUser::class, ['record' => $alvo->uuid])
        ->set('data.avatar', ['forjado' => $alheio->uuid])
        ->call('save');

    expect($alvo->fresh()->avatar_upload_id)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'user.updated')->where('outcome', AuditOutcome::Denied->value)->exists())->toBeTrue();
});

it('EDITAR: upload da PRÓPRIA conta (web ou API) vira a foto dela', function (string $origem): void {
    $alvo = User::fixture();
    $dela = uploadDe($origem === 'web' ? ['user_id' => $alvo->id] : ['tenant_uuid' => $alvo->uuid]);

    Livewire::test(EditUser::class, ['record' => $alvo->uuid])
        ->set('data.avatar', ['dela' => $dela->uuid])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($alvo->fresh()->avatar_upload_id)->toBe($dela->id)
        ->and(AuditEvent::query()->where('outcome', AuditOutcome::Denied->value)->exists())->toBeFalse();
})->with(['web', 'api']);

it('EDITAR: foto enviada AGORA pelo formulário (pela função global de upload) vira a foto da conta editada', function (): void {
    $alvo = User::fixture();

    Livewire::test(EditUser::class, ['record' => $alvo->uuid])
        ->fillForm(['avatar' => pngDeTeste()])
        ->call('save')
        ->assertHasNoFormErrors();

    $upload = Upload::query()->sole();

    // Quem enviou foi o operador (dono web do registro), para a conta editada.
    expect($alvo->fresh()->avatar_upload_id)->toBe($upload->id)
        ->and($upload->user_id)->toBe($this->operador->id);
});

it('CRIAR: foto apontando para upload existente de outra pessoa é recusada ANTES de criar a conta', function (): void {
    $alheio = uploadDe(['user_id' => User::fixture()->id]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Conta Nova',
            'email' => 'nova@example.com',
            'password' => 'Senha-Forte123',
            'password_confirmation' => 'Senha-Forte123',
            'status' => 'active',
        ])
        ->set('data.avatar', ['forjado' => $alheio->uuid])
        ->call('create');

    $evento = AuditEvent::query()->where('action', 'user.created')->sole();

    expect(User::query()->where('email', 'nova@example.com')->exists())->toBeFalse()
        ->and($evento->outcome)->toBe(AuditOutcome::Denied)
        ->and($evento->subject_type)->toBe('user')
        ->and($evento->reason)->toBe(__('admin.users.avatar_not_owned'));
});

it('CRIAR: foto enviada agora pelo formulário é aceita', function (): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Conta Com Foto',
            'email' => 'com-foto@example.com',
            'password' => 'Senha-Forte123',
            'password_confirmation' => 'Senha-Forte123',
            'status' => 'active',
            'avatar' => pngDeTeste(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'com-foto@example.com')->sole()->avatar_upload_id)->toBe(Upload::query()->sole()->id);
});

it('PERFIL do próprio admin: upload de outra pessoa é recusado e registrado; o nome não muda', function (): void {
    $alheio = uploadDe(['user_id' => User::fixture()->id]);

    Livewire::test(Profile::class)
        ->set('data.name', 'Nome Trocado')
        ->set('data.avatar', ['forjado' => $alheio->uuid])
        ->call('save');

    $evento = AuditEvent::query()->where('subject_uuid', $this->operador->uuid)->sole();

    expect($this->operador->fresh()->avatar_upload_id)->toBeNull()
        ->and($this->operador->fresh()->name)->not->toBe('Nome Trocado')
        ->and($evento->action)->toBe('user.updated')
        ->and($evento->outcome)->toBe(AuditOutcome::Denied)
        ->and($evento->reason)->toBe(__('admin.users.avatar_not_owned'));
});

it('a regra em si: dono, foto atual e envio desta requisição passam; o resto não', function (): void {
    $conta = User::fixture();
    $outra = User::fixture();

    $atual = uploadDe(['user_id' => $outra->id]);
    $conta->forceFill(['avatar_upload_id' => $atual->id])->save();

    expect(AvatarUpload::denialFor($conta, [uploadDe(['user_id' => $conta->id])->uuid]))->toBeNull()
        ->and(AvatarUpload::denialFor($conta, [uploadDe(['tenant_uuid' => $conta->uuid])->uuid]))->toBeNull()
        ->and(AvatarUpload::denialFor($conta->fresh(), [$atual->uuid]))->toBeNull()
        ->and(AvatarUpload::denialFor($conta, ['avatars/caminho-atual.png']))->toBeNull()
        ->and(AvatarUpload::denialFor($conta, []))->toBeNull()
        ->and(AvatarUpload::denialFor($conta, [uploadDe(['user_id' => $outra->id])->uuid]))->toBe(__('admin.users.avatar_not_owned'))
        ->and(AvatarUpload::denialFor($conta, [uploadDe([])->uuid]))->toBe(__('admin.users.avatar_not_owned'))
        ->and(AvatarUpload::denialFor(null, [uploadDe(['user_id' => $outra->id])->uuid]))->toBe(__('admin.users.avatar_not_owned'));
});
