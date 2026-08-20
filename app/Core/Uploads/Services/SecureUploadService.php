<?php

declare(strict_types=1);

namespace App\Core\Uploads\Services;

use App\Core\Uploads\Enums\UploadStatus;
use App\Core\Uploads\Exceptions\UploadRejectedException;
use App\Core\Uploads\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FUNÇÃO GLOBAL ÚNICA de upload (ADR-010): toda parte do sistema usa ela.
 *
 * Pipeline:
 *   (a) validação de formulário — DELEGADA ao chamador via Form Request
 *       (arquivo presente, tamanho grosseiro, mimes de formulário);
 *   (b) validação de SEGURANÇA do arquivo (FileSecurityValidator — magic
 *       bytes, allowlist, extensão divergente, scripts/polyglot, PDF com
 *       JavaScript, re-encode de imagem);
 *   (c) nome seguro: uuid + extensão derivada do MIME REAL (o nome original
 *       NUNCA compõe o path);
 *   (d) persistência no disco (Cloudflare R2 em produção — S3-compatível);
 *   (e) registro em banco (vínculo tenant/usuário, sha256 do conteúdo final)
 *       + log estruturado.
 *
 * Arquivo REJEITADO não toca o disco nem o banco — só o log (sem dados
 * sensíveis; nome original sanitizado, LGPD).
 */
final class SecureUploadService
{
    public function __construct(
        private readonly FileSecurityValidator $validator,
    ) {}

    /**
     * Valida e sobe o arquivo, retornando o registro padronizado.
     *
     * @param  UploadedFile  $file  Arquivo já validado em nível de formulário.
     * @param  string|null  $disk  Disco Flysystem de destino (default: config uploads.disk).
     * @param  string|null  $directory  Diretório dentro do disco (default: config uploads.directory).
     * @param  list<string>|null  $allowedTypes  Tipos permitidos (chaves de
     *                                           config uploads.types — ex.: ['image'] no avatar). Default: config.
     *
     * @throws UploadRejectedException Arquivo reprovado na segurança (422).
     * @throws RuntimeException Falha de infraestrutura ao persistir (500).
     */
    public function handle(
        UploadedFile $file,
        ?string $disk = null,
        ?string $directory = null,
        ?array $allowedTypes = null,
    ): Upload {
        $disk ??= (string) config('uploads.disk', 'local');
        $directory ??= (string) config('uploads.directory', 'uploads');

        /** @var list<string> $allowedTypes */
        $allowedTypes ??= array_values((array) config('uploads.allowed_types', ['image', 'pdf']));

        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName());

        $content = file_get_contents($file->getRealPath() ?: '');

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        try {
            $result = $this->validator->validate(
                $content,
                strtolower($file->getClientOriginalExtension()),
                $allowedTypes,
            );

            $this->ensureSizeWithinLimit(strlen($result['content']), $result['mime'], $allowedTypes);
        } catch (UploadRejectedException $exception) {
            $this->logRejected($exception, $originalName, strlen($content), $disk, $directory);

            throw $exception;
        }

        // Nome seguro: uuid + extensão derivada do MIME REAL — o nome
        // original nunca toca o path (checklist item 14).
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$result['extension'];

        if (! Storage::disk($disk)->put($path, $result['content'])) {
            throw new RuntimeException('Falha ao persistir o arquivo no armazenamento.');
        }

        /** @var Upload $upload */
        $upload = Upload::createWithPublicCodeRetry([
            ...$this->ownership(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $originalName,
            'mime' => $result['mime'],
            'size' => strlen($result['content']),
            'sha256' => hash('sha256', $result['content']),
            'status' => UploadStatus::Stored,
        ]);

        Log::info('upload.stored', [
            'upload_uuid' => $upload->uuid,
            'codigo_publico' => $upload->codigo_publico,
            'disk' => $disk,
            'mime' => $result['mime'],
            'size' => $upload->size,
            'sha256' => $upload->sha256,
        ]);

        return $upload;
    }

    /**
     * Tamanho máximo POR TIPO (config) aplicado sobre o conteúdo FINAL
     * (pós re-encode). O Form Request do chamador já fez o corte grosseiro;
     * este é o limite de segurança definitivo.
     *
     * @param  list<string>  $allowedTypes
     */
    private function ensureSizeWithinLimit(int $bytes, string $mime, array $allowedTypes): void
    {
        /** @var array<string, array<string, mixed>> $types */
        $types = (array) config('uploads.types', []);

        foreach ($allowedTypes as $type) {
            $typeConfig = $types[$type] ?? [];

            if (! array_key_exists($mime, (array) ($typeConfig['mimes'] ?? []))) {
                continue;
            }

            $maxKb = (int) ($typeConfig['max_kb'] ?? 0);

            if ($maxKb > 0 && $bytes > $maxKb * 1024) {
                throw new UploadRejectedException('too_large', __('uploads.rejected.too_large', ['max' => $maxKb]));
            }

            return;
        }
    }

    /**
     * Vínculo do registro: na API (ResolveTenant ativo) o tenant_uuid do dono
     * da chave; na web autenticada, o user_id (ADR-010).
     *
     * @return array{tenant_uuid: string|null, user_id: int|null}
     */
    private function ownership(): array
    {
        $tenant = tenant();

        if ($tenant !== null) {
            return ['tenant_uuid' => (string) $tenant->uuid, 'user_id' => null];
        }

        /** @var int|null $userId */
        $userId = auth()->id();

        return ['tenant_uuid' => null, 'user_id' => $userId];
    }

    /**
     * Log estruturado da rejeição — a trilha de segurança das tentativas
     * (arquivo malicioso não é persistido em lugar nenhum, mas a tentativa
     * fica registrada, com o nome original sanitizado).
     */
    private function logRejected(
        UploadRejectedException $exception,
        string $originalName,
        int $size,
        string $disk,
        string $directory,
    ): void {
        Log::warning('upload.rejected', [
            'reason' => $exception->reason,
            'original_name' => $originalName,
            'size' => $size,
            'disk' => $disk,
            'directory' => $directory,
        ]);
    }

    /**
     * Nome original SOMENTE para exibição: sem diretórios/traversal, sem
     * caracteres de controle, truncado. Nunca usado em path.
     */
    private function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return Str::limit($name === '' ? 'arquivo' : $name, 255, '');
    }
}
