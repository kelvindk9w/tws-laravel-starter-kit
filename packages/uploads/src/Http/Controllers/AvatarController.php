<?php

declare(strict_types=1);

namespace Twstec\Kit\Uploads\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Uploads\Exceptions\UploadRejectedException;
use Twstec\Kit\Uploads\Http\Requests\UpdateAvatarRequest;
use Twstec\Kit\Uploads\Http\Resources\UploadResource;
use Twstec\Kit\Uploads\Services\SecureUploadService;

/**
 * Avatar do perfil (web autenticada): POST /settings/avatar.
 *
 * Prova o reuso da MESMA função global de upload fora da API: aqui o
 * registro sai vinculado ao user_id da sessão (na API é o tenant_uuid).
 * Restrito a imagens — passam pelo re-encode GD do SecureUploadService.
 *
 * A ROTA é do aplicativo (rota web, no grupo autenticado dele, com o limite
 * `throttle:sensitive` do foundation): o pacote não registra rota web.
 */
final class AvatarController
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

        // Vincula o upload validado como avatar do perfil.
        $request->user()->forceFill(['avatar_upload_id' => $upload->id])->save();

        return UploadResource::make($upload)
            ->additional(['message' => __('uploads.avatar_updated')])
            ->response()
            ->setStatusCode(201);
    }
}
