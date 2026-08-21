@props(['title' => null])

{{-- Cartão de conteúdo. <x-card title="…">corpo</x-card> + <x-slot:footer> opcional. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    @if ($title !== null)
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        </div>
    @endif
    <div class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $slot }}</div>
    @isset($footer)
        <div class="border-t border-gray-200 px-5 py-3 dark:border-gray-800">{{ $footer }}</div>
    @endisset
</div>
