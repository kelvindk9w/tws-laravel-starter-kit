@props([
    'label' => null,
    'name' => null,
    'checked' => false,
    'disabled' => false,
])

{{-- Checkbox com label. <x-checkbox label="Lembrar de mim" name="remember" /> --}}
<label {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 '.($disabled ? 'opacity-60' : '')]) }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked($checked)
        @disabled($disabled)
        class="h-4 w-4 rounded border-gray-300 text-(--brand) accent-(--brand) focus:ring-(--brand)/50 dark:border-gray-600"
    >
    <span>{{ $label ?? $slot }}</span>
</label>
