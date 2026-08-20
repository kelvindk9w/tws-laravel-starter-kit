<?php

declare(strict_types=1);

namespace App\Core\Uploads\Http\Controllers;

use App\Core\Uploads\Exceptions\UploadRejectedException;
use App\Core\Uploads\Http\Requests\StoreUploadRequest;
use App\Core\Uploads\Http\Resources\UploadResource;
use App\Core\Uploads\Services\SecureUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint de exemplo da API v1 (Fase 5): POST /api/v1/uploads
 * (scope uploads:create). Prova o reuso da função global única de upload
 * (SecureUploadService) no fluxo tenant — o registro sai vinculado ao
 * tenant_uuid do dono da chave (ADR-010).
 */
final class UploadController extends Controller
{
    public function __construct(private readonly SecureUploadService $uploads) {}

    /**
     * POST /api/v1/uploads — validação de formulário (Form Request) →
     * validação de segurança do arquivo → upload → retorno padronizado.
     */
    public function store(StoreUploadRequest $request): JsonResponse
    {
        /** @var array{directory?: string} $data */
        $data = $request->validated();

        try {
            $upload = $this->uploads->handle(
                $request->file('file'),
                directory: $data['directory'] ?? null,
            );
        } catch (UploadRejectedException $exception) {
            // Rejeição de segurança = erro de validação do campo (422).
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return UploadResource::make($upload)
            ->additional(['message' => __('uploads.stored')])
            ->response()
            ->setStatusCode(201);
    }
}
