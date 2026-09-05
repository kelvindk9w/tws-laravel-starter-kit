<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Core\Auth\Models\User;
use App\Filament\Resources\Users\Support\UserAdminGuard;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Edição de usuário pelo super admin.
 *
 * Guardas de SERVIDOR (UserAdminGuard) antes de gravar: conta demo é
 * intocável, o admin não se bloqueia e o último admin ativo não perde a
 * flag nem o acesso — esconder o botão não é proteção.
 *
 * `status`/`is_admin` gravados por forceFill (nunca mass assignment).
 */
final class EditUser extends EditRecord
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
        return __('admin.users.edit');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('admin.users.updated_success');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('admin.users.delete'))
                ->modalHeading(__('admin.users.delete_heading'))
                ->successNotificationTitle(__('admin.users.deleted_success'))
                ->visible(fn (): bool => UserAdminGuard::deleteDenial($this->getRecord(), auth()->user()) === null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        if ($motivo = UserAdminGuard::updateDenial($record, $data, auth()->user())) {
            Notification::make()->danger()->title(__('admin.users.action_denied'))->body($motivo)->send();

            throw new Halt;
        }

        $atributos = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ];

        // Senha em branco = manter a atual (o campo já vem desidratado).
        if (filled($data['password'] ?? null)) {
            $atributos['password'] = $data['password'];
        }

        $record->forceFill($atributos)->save();

        return $record;
    }
}
