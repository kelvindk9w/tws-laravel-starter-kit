@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'disabled' => false,
])

{{-- Select com label e estado de erro. Opções via slot. --}}
<div {{ $attributes->only('class') }}>
    @if ($label !== null)
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @disabled($disabled)
        {{ $attributes->except('class')->merge(['class' =>
            'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 transition focus:outline-none focus:ring-2 focus:ring-(--brand)/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900 dark:text-gray-100 '
            .(! empty($error)
                ? 'border-red-500 focus:border-red-500'
                : 'border-gray-300 focus:border-(--brand) dark:border-gray-700'),
        ]) }}
    >{{ $slot }}</select>
    @if (! empty($error))
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>
