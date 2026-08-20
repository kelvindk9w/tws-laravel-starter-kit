<?php

declare(strict_types=1);

namespace App\Core\Security;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extração padronizada dos inputs da requisição para o pipeline de
 * segurança e logs (ADR-005: receber → validar → sanitizar → persistir).
 *
 * Arquivos enviados NUNCA têm conteúdo lido aqui — apenas metadados
 * (nome, MIME declarado, tamanho). O nome original do arquivo também é
 * inspecionado pela validação de segurança (pode carregar path traversal).
 */
final class RequestInputs
{
    /**
     * Retorna query + corpo da requisição, com arquivos substituídos por
     * metadados seguros (arrays de strings escalares).
     *
     * @return array<string, mixed>
     */
    public static function extract(Request $request): array
    {
        $data = $request->except(array_keys($request->allFiles()));

        foreach ($request->allFiles() as $key => $file) {
            $data[$key] = self::describeFile($file);
        }

        return $data;
    }

    /**
     * Metadados seguros de um arquivo enviado (nunca o conteúdo).
     *
     * @param  UploadedFile|array<array-key, mixed>  $file
     * @return array<string, mixed>
     */
    private static function describeFile(UploadedFile|array $file): array
    {
        if (is_array($file)) {
            return array_map(fn (mixed $item): mixed => $item instanceof UploadedFile || is_array($item) ? self::describeFile($item) : '[arquivo]', $file);
        }

        return [
            'nome_arquivo' => $file->getClientOriginalName(),
            'mime_declarado' => $file->getClientMimeType(),
            'tamanho_bytes' => $file->getSize(),
        ];
    }
}
