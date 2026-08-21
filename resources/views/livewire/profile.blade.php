<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('panel.profile.title') }}</h1>

    {{-- Dados básicos ------------------------------------------------------ --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="font-semibold">{{ __('panel.profile.data_heading') }}</h2>

        @if (session('profile_status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('profile_status') }}</p>
        @endif

        <form wire:submit="updateProfile" class="mt-4 space-y-4">
            <div>
                <label for="name" class="text-sm font-medium">{{ __('panel.common.name') }}</label>
                <input id="name" type="text" wire:model="name" required
                       class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="locale" class="text-sm font-medium">{{ __('panel.profile.locale_label') }}</label>
                <select id="locale" wire:model="locale"
                        class="mt-1 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 dark:border-gray-700">
                    @foreach (platform()->availableLocales as $availableLocale)
                        <option value="{{ $availableLocale }}">{{ __("ui.locale.names.{$availableLocale}") }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('panel.profile.locale_hint') }}</p>
                @error('locale') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">{{ __('auth.ui.email') }}</label>
                <p class="mt-1 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $user->email }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('panel.profile.email_readonly') }}</p>
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.save') }}</button>
        </form>
    </section>

    {{-- Aparência (tema claro/escuro/sistema) -------------------------------- --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="font-semibold">{{ __('panel.profile.theme_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.profile.theme_hint') }}</p>

        <div class="mt-4 inline-flex gap-1 rounded-lg border border-gray-200 p-1 dark:border-gray-700" role="group" aria-label="{{ __('panel.profile.theme_heading') }}">
            @foreach (['system' => 'computer-desktop', 'light' => 'sun', 'dark' => 'moon'] as $themeValue => $themeIcon)
                <button type="button" data-theme-set="{{ $themeValue }}" aria-pressed="false"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-gray-600 transition-[background-color,color] duration-150 ease-(--ease-out) dark:text-gray-300">
                    <x-ui-icon :name="$themeIcon" class="h-4 w-4" />
                    {{ __("ui.theme.{$themeValue}") }}
                </button>
            @endforeach
        </div>
    </section>

    {{-- Avatar (função global de upload da Fase 5) --------------------------- --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="font-semibold">{{ __('panel.profile.avatar_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.profile.avatar_hint') }}</p>

        @if (session('avatar_status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('avatar_status') }}</p>
        @endif

        <form wire:submit="updateAvatar" class="mt-4 flex items-center gap-4">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover">
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand text-xl font-semibold text-brand-foreground">{{ mb_substr($user->name, 0, 1) }}</span>
            @endif
            <div class="flex-1">
                <input type="file" wire:model="avatar" accept="image/*"
                       class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-2 file:text-brand-foreground">
                @error('avatar') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="avatar" class="mt-1 text-xs text-gray-500">…</div>
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground" @disabled(! $avatar)>{{ __('panel.common.save') }}</button>
        </form>
    </section>

    {{-- Senha de login -------------------------------------------------------- --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="font-semibold">{{ __('panel.profile.password_heading') }}</h2>

        @if (session('password_status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('password_status') }}</p>
        @endif

        <form wire:submit="updatePassword" class="mt-4 space-y-4">
            <x-input :label="__('panel.profile.current_password')" name="currentPassword" type="password"
                     wire:model="currentPassword" autocomplete="current-password"
                     :error="$errors->first('currentPassword')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input :label="__('auth.ui.new_password')" name="password" type="password"
                         wire:model="password" autocomplete="new-password"
                         :error="$errors->first('password')" />
                <x-input :label="__('auth.ui.password_confirmation')" name="passwordConfirmation" type="password"
                         wire:model="passwordConfirmation" autocomplete="new-password"
                         :error="$errors->first('passwordConfirmation')" />
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.save') }}</button>
        </form>
    </section>

    {{-- Senha de transação (ADR-006 — hash separado) --------------------------- --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold">{{ __('panel.profile.transaction_password_heading') }}</h2>
            <span @class(['rounded-full px-2.5 py-1 text-xs font-medium',
                          'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' => $user->hasTransactionPassword(),
                          'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => ! $user->hasTransactionPassword()])>
                {{ $user->hasTransactionPassword() ? __('panel.profile.transaction_password_set') : __('panel.profile.transaction_password_not_set') }}
            </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.profile.transaction_password_hint') }}</p>

        @if (session('transaction_password_status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('transaction_password_status') }}</p>
        @endif

        <form wire:submit="updateTransactionPassword" class="mt-4 space-y-4">
            @if ($user->hasTransactionPassword())
                <x-input :label="__('auth.ui.current_transaction_password')" name="currentTransactionPassword" type="password"
                         wire:model="currentTransactionPassword" autocomplete="off"
                         :error="$errors->first('currentTransactionPassword')" />
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input :label="__('auth.ui.new_transaction_password')" name="transactionPassword" type="password"
                         wire:model="transactionPassword" autocomplete="new-password"
                         :error="$errors->first('transactionPassword')" />
                <x-input :label="__('auth.ui.password_confirmation')" name="transactionPasswordConfirmation" type="password"
                         wire:model="transactionPasswordConfirmation" autocomplete="new-password"
                         :error="$errors->first('transactionPasswordConfirmation')" />
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.save') }}</button>
        </form>
    </section>
</div>
