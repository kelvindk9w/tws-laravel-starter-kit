@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'disabled' => false,
])

{{-- Campo de texto com label, hint e estado de erro. <x-input label="E-mail" name="email" />
     type="password" embute o botão "olho" (revelar/ocultar) à direita —
     comportamento em resources/js/ui.js ([data-password-toggle]). --}}
@php
    $isPassword = $type === 'password';
    $inputClasses =
        'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-(--brand)/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900 dark:text-gray-100 '
        .($isPassword ? 'pr-10 ' : '')
        .(! empty($error)
            ? 'border-red-500 focus:border-red-500'
            : 'border-gray-300 focus:border-(--brand) dark:border-gray-700');
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label !== null)
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif
    @if ($isPassword)
        <div class="relative">
            <input
                id="{{ $name }}"
                name="{{ $name }}"
                type="password"
                @disabled($disabled)
                {{ $attributes->except('class')->merge(['class' => $inputClasses]) }}
            >
            <button
                type="button"
                data-password-toggle
                aria-label="{{ __('ui.password.show') }}"
                data-label-show="{{ __('ui.password.show') }}"
                data-label-hide="{{ __('ui.password.hide') }}"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition-colors duration-150 ease-(--ease-out) hover:text-gray-600 dark:hover:text-gray-300"
            >
                <span data-password-icon="show"><x-ui-icon name="eye" class="h-4 w-4" /></span>
                <span data-password-icon="hide" class="hidden"><x-ui-icon name="eye-slash" class="h-4 w-4" /></span>
            </button>
        </div>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @disabled($disabled)
            {{ $attributes->except('class')->merge(['class' => $inputClasses]) }}
        >
    @endif
    @if (! empty($error))
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @elseif ($hint !== null)
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
