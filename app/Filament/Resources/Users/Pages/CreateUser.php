<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

/**
 * Criação de usuário pelo super admin.
 *
 * `status` e `is_admin` NÃO são mass-assignable (ADR-011: a flag de admin
 * nunca entra por atribuição em massa) — por isso a criação é feita com
 * forceFill explícito em vez do create() padrão do Filament.
 */
final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Teto de largura do formulário — mesma medida das demais telas de
     * edição do painel (crítica de design: formulário sem teto).
     */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::FourExtraLarge;
    }

    public function getTitle(): string
    {
        return __('admin.users.create');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('admin.users.created_success');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;

        // O cast `hashed` do model cuida do Argon2id da senha.
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => $data['status'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            // Conta criada pelo admin já nasce com e-mail verificado: quem
            // cadastrou é o operador do painel, não um visitante anônimo.
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
