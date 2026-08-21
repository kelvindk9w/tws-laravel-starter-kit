<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TransactionPasswordService;
use App\Core\Uploads\Exceptions\UploadRejectedException;
use App\Core\Uploads\Services\SecureUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Perfil do usuário (Fase 6): dados, senha de login, senha de transação e
 * avatar — tudo na MESMA tela (ADR-005: simplicidade máxima, sem labirinto).
 *
 * Reuso (sem duplicar lógica):
 * - Senha de transação → TransactionPasswordService (mesma regra da Fase 3).
 * - Avatar → SecureUploadService (função global de upload da Fase 5: valida
 *   o CONTEÚDO do arquivo e faz re-encode GD antes de persistir).
 */
final class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $locale = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $currentTransactionPassword = '';

    public string $transactionPassword = '';

    public string $transactionPasswordConfirmation = '';

    public ?TemporaryUploadedFile $avatar = null;

    public function mount(): void
    {
        $this->name = (string) $this->user()->name;
        $this->locale = $this->user()->preferredLocale();
    }

    /**
     * Atualiza os dados básicos (nome, idioma). O e-mail é a chave de acesso
     * da conta — troca de e-mail exige fluxo próprio de verificação (futuro).
     */
    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(platform()->availableLocales)],
        ], [], [
            'locale' => __('panel.profile.locale_label'),
        ]);

        $this->user()->forceFill(['name' => $validated['name'], 'locale' => $validated['locale']])->save();

        // Reflete imediatamente na interface da resposta (o middleware
        // SetLocale garante nas próximas requisições).
        app()->setLocale($validated['locale']);

        session()->flash('profile_status', __('panel.common.saved'));
    }

    /**
     * Troca da senha de LOGIN: exige a senha atual e aplica as mesmas
     * regras de força do cadastro (config/auth.php password_rules).
     */
    public function updatePassword(): void
    {
        $validated = $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => [
                'required',
                Password::min((int) config('auth.password_rules.min_length', 12))
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
            'passwordConfirmation' => ['required', 'same:password'],
        ], [], [
            'currentPassword' => __('panel.profile.current_password'),
            'password' => __('auth.ui.new_password'),
            'passwordConfirmation' => __('auth.ui.password_confirmation'),
        ]);

        $user = $this->user();

        if (! Hash::check($validated['currentPassword'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => __('panel.profile.current_password_invalid'),
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        session()->flash('password_status', __('panel.profile.password_updated'));
    }

    /**
     * Define/altera a senha de TRANSAÇÃO — delega ao TransactionPasswordService
     * (regra única da Fase 3: hash separado, ≠ senha de login, atual exigida).
     */
    public function updateTransactionPassword(TransactionPasswordService $service): void
    {
        $validated = $this->validate([
            'currentTransactionPassword' => [
                $this->user()->hasTransactionPassword() ? 'required' : 'nullable',
                'string',
            ],
            'transactionPassword' => [
                'required',
                Password::min((int) config('auth.transaction_password.min_length', 8))
                    ->letters()
                    ->numbers(),
            ],
            'transactionPasswordConfirmation' => ['required', 'same:transactionPassword'],
        ], [], [
            'currentTransactionPassword' => __('auth.ui.current_transaction_password'),
            'transactionPassword' => __('auth.ui.new_transaction_password'),
        ]);

        try {
            $service->update(
                $this->user(),
                $validated['transactionPassword'],
                $validated['currentTransactionPassword'] ?? null,
            );
        } catch (ValidationException $exception) {
            // Mapeia as chaves do service (snake_case) para os campos do form.
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $key): array => [lcfirst(\Str::camel($key)) => $messages])
                    ->all(),
            );
        }

        $this->reset('currentTransactionPassword', 'transactionPassword', 'transactionPasswordConfirmation');
        session()->flash('transaction_password_status', __('auth.transaction_password.saved'));
    }

    /**
     * Avatar: mesma função global de upload seguro da Fase 5 (validação por
     * conteúdo + re-encode GD). O registro fica vinculado ao perfil.
     */
    public function updateAvatar(SecureUploadService $uploads): void
    {
        $maxKb = (int) setting('uploads.types.image.max_kb');

        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ]);

        try {
            $upload = $uploads->handle($this->avatar, directory: 'avatars', allowedTypes: ['image']);
        } catch (UploadRejectedException $exception) {
            throw ValidationException::withMessages(['avatar' => $exception->getMessage()]);
        }

        $this->user()->forceFill(['avatar_upload_id' => $upload->id])->save();

        $this->reset('avatar');
        session()->flash('avatar_status', __('panel.profile.avatar_updated'));
    }

    public function render(): View
    {
        return view('livewire.profile', [
            'user' => $this->user()->fresh(['avatar']),
        ])->title(__('panel.profile.title'));
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
