<?php

declare(strict_types=1);

namespace App\Core\Uploads\Models;

use App\Core\Auth\Models\User;
use App\Core\Identifiers\HasPublicCode;
use App\Core\Identifiers\RoutesByUuid;
use App\Core\Uploads\Enums\UploadStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Registro de upload (Fase 5 — ADR-010).
 *
 * Só existe registro para arquivo que PASSOU pela validação de segurança
 * (rejeitados não tocam o banco nem o disco — só o log). O `path` é sempre
 * uuid + extensão derivada do MIME real; o nome original é guardado
 * sanitizado, apenas para exibição.
 *
 * Vínculo: na API, `tenant_uuid` (uuid do dono da chave, via ResolveTenant);
 * na web, `user_id` do usuário autenticado.
 *
 * Identificadores (3 camadas — ADR-010): `id` interno nunca exposto; `uuid`
 * externo; `codigo_publico` legível UPL-xxxxxx (UNIQUE no banco).
 */
#[Fillable(['tenant_uuid', 'user_id', 'disk', 'path', 'original_name', 'mime', 'size', 'sha256', 'status'])]
class Upload extends Model
{
    use HasPublicCode, HasUuids, RoutesByUuid;

    /**
     * Prefixo do código público legível (ADR-010): UPL-xxxxxx.
     */
    protected const PUBLIC_CODE_PREFIX = 'UPL';

    /**
     * Default da instância nova (espelha o default da migration).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'stored',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'status' => UploadStatus::class,
        ];
    }

    /**
     * Dono do upload na web (sessão). Na API o vínculo é o tenant_uuid.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * URL de acesso ao arquivo. NUNCA bucket público: tenta primeiro a URL
     * temporária assinada (S3/R2 e local com serve); se o driver não suportar,
     * cai para a URL padrão do disco.
     */
    public function url(?DateTimeInterface $expiration = null): string
    {
        $disk = Storage::disk((string) $this->disk);

        $expiration ??= now()->addMinutes((int) config('uploads.temporary_url_minutes', 15));

        try {
            return $disk->temporaryUrl((string) $this->path, $expiration);
        } catch (Throwable) {
            return $disk->url((string) $this->path);
        }
    }
}
