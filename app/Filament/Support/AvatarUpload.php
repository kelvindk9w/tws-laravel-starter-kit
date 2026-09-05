<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Core\Auth\Models\User;
use App\Core\Uploads\Exceptions\UploadRejectedException;
use App\Core\Uploads\Models\Upload;
use App\Core\Uploads\Rules\SafeFile;
use App\Core\Uploads\Services\SecureUploadService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campo de FOTO DE PERFIL do super admin, em um lugar só (cadastro de
 * usuário e perfil do próprio admin usam este mesmo campo).
 *
 * O ponto que não podia ser negociado: o Filament, de fábrica, grava o
 * arquivo direto no disco. Isso pularia a FUNÇÃO GLOBAL DE UPLOAD do kit
 * (SecureUploadService — ADR-010) e, com ela, a validação por magic bytes,
 * o re-encode GD, o nome derivado do MIME real e o registro em `uploads`.
 * Ou seja: o /admin viraria a única porta do sistema por onde um arquivo
 * entra sem passar pela lei.
 *
 * Por isso `saveUploadedFileUsing()` delega ao service e devolve o UUID do
 * Upload criado — é esse uuid que as páginas transformam em
 * `avatar_upload_id`. E `SafeFile` roda antes, na validação, para que um
 * .txt renomeado para .png apareça como erro embaixo do campo, e não como
 * erro de servidor depois do "Salvar".
 *
 * A foto é servida por URL ASSINADA e de curta duração: o campo declara
 * `visibility('private')`, que é o que faz o Filament pedir `temporaryUrl()`
 * ao disco em vez de montar uma URL pública.
 */
final class AvatarUpload
{
    public const DIRECTORY = 'avatars';

    /**
     * O campo do formulário. `$name` não é coluna do model: o valor é lido
     * e gravado pelas páginas (ver applyTo()).
     */
    public static function field(string $name = 'avatar'): FileUpload
    {
        $maxKb = (int) data_get(config('uploads.types'), 'image.max_kb', 5120);

        return FileUpload::make($name)
            ->label(__('admin.users.avatar'))
            ->helperText(__('admin.users.avatar_hint', ['max' => $maxKb]))
            ->avatar()
            ->image()
            ->imagePreviewHeight('96')
            ->disk((string) config('uploads.disk', 'local'))
            ->directory(self::DIRECTORY)
            // Sem isto o Filament monta URL pública: a política do kit é
            // documento nunca em bucket público (ADR-010).
            ->visibility('private')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize($maxKb)
            ->rules([new SafeFile(['image'])])
            // A gravação é do service, não do Filament.
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, SecureUploadService $uploads): ?string {
                try {
                    return $uploads->handle($file, directory: self::DIRECTORY, allowedTypes: ['image'])->uuid;
                } catch (UploadRejectedException) {
                    // A regra SafeFile já barrou este caso na validação; se
                    // chegou aqui, o arquivo mudou entre uma e outra: some
                    // sem gravar nada.
                    return null;
                }
            });
    }

    /**
     * O que o formulário devolve no campo (uuid de Upload novo, caminho do
     * avatar atual, ou vazio) virando o vínculo do usuário.
     *
     * - uuid de upload novo  → troca a foto;
     * - caminho já existente → mantém a que está lá (o usuário não mexeu);
     * - vazio                → remove a foto (o vínculo volta a null e as
     *                          iniciais assumem — InitialsAvatarProvider).
     */
    public static function applyTo(User $user, mixed $state): void
    {
        $valores = array_values(array_filter(
            is_array($state) ? $state : [$state],
            fn ($valor): bool => is_string($valor) && $valor !== '',
        ));

        if ($valores === []) {
            $user->forceFill(['avatar_upload_id' => null])->save();
            $user->unsetRelation('avatar');

            return;
        }

        // O campo pode carregar o caminho da foto ATUAL junto com o uuid da
        // recém-enviada. Vence o Upload mais novo: é o que a pessoa acabou
        // de escolher.
        $upload = Upload::query()->whereIn('uuid', $valores)->orderByDesc('id')->first();

        if ($upload === null) {
            // Só caminhos de arquivo já existentes: nada mudou.
            return;
        }

        $user->forceFill(['avatar_upload_id' => $upload->id])->save();

        // A relação pode ter sido carregada (vazia) antes da troca — o
        // avatar do cabeçalho é desenhado na MESMA resposta e mostraria as
        // iniciais de novo, como se o upload não tivesse funcionado.
        $user->unsetRelation('avatar');
    }

    /**
     * Estado inicial do campo ao abrir o formulário: o caminho do avatar
     * atual, para o Filament desenhar a prévia.
     */
    public static function stateFor(?User $user): ?string
    {
        // Consulta direta, não `$user->avatar->path`: a relação pode estar
        // carregada e desatualizada na instância autenticada.
        return $user === null ? null : $user->avatar()->value('path');
    }
}
