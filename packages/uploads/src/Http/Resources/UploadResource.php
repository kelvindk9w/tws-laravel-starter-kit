<?php

declare(strict_types=1);

namespace Twstec\Kit\Uploads\Http\Resources;

use Illuminate\Http\Request;
use Twstec\Kit\Foundation\Http\Resources\BaseResource;
use Twstec\Kit\Uploads\Models\Upload;

/**
 * Retorno padronizado do upload (sempre via Resource, nunca o model cru): uuid, código público, path,
 * url temporária assinada, MIME REAL, tamanho e hash sha256 do conteúdo
 * persistido. O `id` interno nunca sai.
 *
 * @mixin Upload
 */
final class UploadResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->publicIdentifiers($this->resource),
            'path' => $this->path,
            'url' => $this->url(),
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'status' => $this->status->value,
            'created_at' => $this->isoTimestamp($this->created_at),
        ];
    }
}
