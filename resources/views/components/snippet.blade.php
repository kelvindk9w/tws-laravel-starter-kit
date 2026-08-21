@props(['code'])

{{-- Snippet de código copiável. <x-snippet code='<x-button>OK</x-button>' />
    Cópia e feedback via resources/js/ui.js ([data-copy]). --}}
<span class="inline-flex max-w-full items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 py-1 pl-2 pr-1 dark:border-gray-800 dark:bg-gray-900">
    <code class="max-w-full overflow-x-auto whitespace-nowrap text-xs text-gray-600 dark:text-gray-400" style="scrollbar-width: thin">{{ $code }}</code>
    <button
        type="button"
        data-copy="{{ $code }}"
        data-copied-text="{{ __('showcase.snippets.copied') }}"
        title="{{ __('showcase.snippets.copy') }}"
        class="inline-flex shrink-0 items-center gap-1 rounded px-1.5 py-0.5 text-xs text-gray-500 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-200 hover:text-gray-700 active:scale-[0.97] motion-reduce:active:scale-100 dark:hover:bg-gray-800 dark:hover:text-gray-300"
    >
        <x-ui-icon name="clipboard-document" class="h-3.5 w-3.5" />
        <span data-copy-label>{{ __('showcase.snippets.copy') }}</span>
    </button>
</span>
