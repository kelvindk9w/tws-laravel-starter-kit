{{-- Toggle de tema de 3 estados (Sistema → Claro → Escuro → Sistema).
     <x-theme-toggle /> — o estado inicial e o clique são resolvidos por
     resources/js/ui.js ([data-theme-toggle]); os três ícones ficam no
     markup para o JS alternar sem depender de rede. --}}
<button
    type="button"
    data-theme-toggle
    title="{{ __('ui.theme.toggle') }}"
    aria-label="{{ __('ui.theme.toggle') }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg border border-gray-200 p-2 text-gray-600 transition-[transform,background-color,border-color,color] duration-150 ease-(--ease-out) hover:bg-gray-100 active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800']) }}
>
    <span data-theme-icon="system"><x-ui-icon name="computer-desktop" class="h-4 w-4" /></span>
    <span data-theme-icon="light" class="hidden"><x-ui-icon name="sun" class="h-4 w-4" /></span>
    <span data-theme-icon="dark" class="hidden"><x-ui-icon name="moon" class="h-4 w-4" /></span>
</button>
