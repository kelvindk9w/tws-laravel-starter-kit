@props([
    'label' => null,
    'name' => null,
    'checked' => false,
    'disabled' => false,
])

{{-- Interruptor (toggle) — checkbox estilizado. <x-toggle label="Notificações" name="notifications" /> --}}
<label {{ $attributes->merge(['class' => 'inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300 '.($disabled ? 'cursor-not-allowed opacity-60' : '')]) }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked($checked)
        @disabled($disabled)
        class="peer sr-only"
    >
    <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-gray-300 transition peer-checked:bg-(--brand) peer-focus-visible:ring-2 peer-focus-visible:ring-(--brand)/50 peer-disabled:opacity-60 dark:bg-gray-700 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
    <span>{{ $label ?? $slot }}</span>
</label>
