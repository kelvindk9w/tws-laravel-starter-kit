<?php

declare(strict_types=1);

namespace App\Core\Uploads\Http\Controllers;

use App\Core\Uploads\Exceptions\UploadRejectedException;
use App\Core\Uploads\Http\Requests\UpdateAvatarRequest;
use App\Core\Uploads\Http\Resources\UploadResource;
use App\Core\Uploads\Services\SecureUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Avatar do perfil (web autenticada): POST /settings/avatar.
 *
 * Prova o reuso da MESMA função global de upload fora da API: aqui o
 * registro sai vinculado ao user_id da sessão (na API é o tenant_uuid).
 * Restrito a imagens — passam pelo re-encode GD do SecureUploadService.
 */
final class AvatarController extends Controller
{
    public function __construct(private readonly SecureUploadService $uploads) {}

    public function update(UpdateAvatarRequest $request): JsonResponse
    {
        try {
            $upload = $this->uploads->handle(
                $request->file('avatar'),
                directory: 'avatars',
                allowedTypes: ['image'],
            );
        } catch (UploadRejectedException $exception) {
            throw ValidationException::withMessages([
                'avatar' => $exception->getMessage(),
            ]);
        }

        // Vincula o upload validado como avatar do perfil (Fase 6).
        $request->user()->forceFill(['avatar_upload_id' => $upload->id])->save();

        return UploadResource::make($upload)
            ->additional(['message' => __('uploads.avatar_updated')])
            ->response()
            ->setStatusCode(201);
    }
}
