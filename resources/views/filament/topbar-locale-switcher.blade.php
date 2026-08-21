{{-- Seletor de idioma do super admin (/admin — Filament 5), registrado via
     render hook TOPBAR_END no AdminPanelProvider. Mesmo formato compacto do
     <x-locale-switcher> (bandeira + sigla) e mesma rota locale.switch —
     o SetLocale do painel resolve cookie/preferência igual ao resto do app.

     Estilo inline de propósito: o CSS do painel Filament é um build próprio
     (não carrega os utilitários do app), e a CSP do /admin permite inline.
     O onchange direto dispensa o ui.js (não carregado no painel). --}}
@php
    $currentLocale = app()->getLocale();
    $localeFlags = ['pt_BR' => '🇧🇷', 'en' => '🇺🇸', 'es' => '🇪🇸'];
    $localeCodes = ['pt_BR' => 'PT', 'en' => 'EN', 'es' => 'ES'];
@endphp

<select
    aria-label="{{ __('ui.locale.label') }}"
    onchange="if (this.value) window.location.href = this.value"
    style="cursor: pointer; border-radius: 0.5rem; border: 1px solid rgb(0 0 0 / 0.1); background: transparent; padding: 0.375rem 0.5rem; font-size: 0.875rem; color: inherit;"
    class="dark:!border-white/10"
>
    @foreach (platform()->availableLocales as $locale)
        <option value="{{ route('locale.switch', $locale) }}" @selected($currentLocale === $locale)>
            {{ ($localeFlags[$locale] ?? '🌐').' '.($localeCodes[$locale] ?? strtoupper($locale)) }}
        </option>
    @endforeach
</select>
