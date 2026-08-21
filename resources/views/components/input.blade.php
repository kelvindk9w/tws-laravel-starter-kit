@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'disabled' => false,
])

{{-- Campo de texto com label, hint e estado de erro. <x-input label="E-mail" name="email" /> --}}
<div {{ $attributes->only('class') }}>
    @if ($label !== null)
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @disabled($disabled)
        {{ $attributes->except('class')->merge(['class' =>
            'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-(--brand)/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900 dark:text-gray-100 '
            .($error !== null
                ? 'border-red-500 focus:border-red-500'
                : 'border-gray-300 focus:border-(--brand) dark:border-gray-700'),
        ]) }}
    >
    @if ($error !== null)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @elseif ($hint !== null)
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
