<?php

declare(strict_types=1);

namespace App\Core\Uploads\Concerns;

use App\Core\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de perfil: o vínculo do model (o usuário) com um upload validado.
 *
 * Mora no módulo de Uploads, e não no model do usuário, porque é o upload
 * que sabe o que é uma foto de perfil — o módulo de autenticação não precisa
 * conhecer uploads. O model que usar esta trait precisa da coluna
 * `avatar_upload_id`.
 *
 * @mixin Model
 */
trait HasAvatar
{
    /**
     * Avatar do perfil (upload validado pela função global de upload, SecureUploadService).
     *
     * @return BelongsTo<Upload, $this>
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'avatar_upload_id');
    }

    /**
     * URL (assinada) do avatar, ou null quando não definido.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar?->url();
    }
}
