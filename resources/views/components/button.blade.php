@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

{{-- Botão padrão do kit. <x-button>…</x-button> ou <x-button href="…"> (link).
     Motion: transitions com propriedades explícitas (nunca `all`), :active
     scale(0.97) = feedback instantâneo de pressão; desligado com reduced-motion. --}}
@php
    $variantClasses = match ($variant) {
        'primary' => 'bg-(--brand) text-white hover:brightness-110',
        'secondary' => 'bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700',
        'ghost' => 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
        'outline' => 'border border-(--brand) bg-transparent text-(--brand) hover:bg-(--brand)/10',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        default => 'bg-(--brand) text-white hover:brightness-110',
    };
    $sizeClasses = match ($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
        default => 'px-4 py-2 text-sm',
    };
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium'
        .' transition-[transform,background-color,border-color,color,filter] duration-150 ease-(--ease-out)'
        .' active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100'
        .' disabled:cursor-not-allowed disabled:opacity-50 '.$sizeClasses.' '.$variantClasses;
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
