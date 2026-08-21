{{-- Seletor de idioma do kit (ADR-007). <x-locale-switcher />
     Navega para a rota locale.switch (cookie + preferência da conta);
     o comportamento vive em resources/js/ui.js ([data-locale-switch]) —
     sem JS inline (CSP-friendly). Sem JS, o select não faz nada: as navs
     públicas também expõem os links no rodapé como fallback? Não — o kit
     exige JS para a UI rica; o select mantém a11y (label + teclado). --}}
@php($currentLocale = app()->getLocale())

<select
    data-locale-switch
    aria-label="{{ __('ui.locale.label') }}"
    {{ $attributes->merge(['class' => 'cursor-pointer rounded-lg border border-gray-200 bg-transparent px-2 py-1.5 text-sm text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800']) }}
>
    @foreach (platform()->availableLocales as $locale)
        <option value="{{ route('locale.switch', $locale) }}" @selected($currentLocale === $locale)>
            {{ __("ui.locale.names.{$locale}") }}
        </option>
    @endforeach
</select>
