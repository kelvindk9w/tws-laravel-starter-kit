{{-- SEGURANÇA DE FÁBRICA — bento com as seis peças.

     Uma célula VIVA por vez (resources/js/landing-v3/bento.js): seis realces
     simultâneos seriam ruído; um de cada vez conta que são seis partes do
     MESMO sistema. Sem JS, sem WebGL ou com `prefers-reduced-motion` o bento
     fica inteiro e parado — as bordas já separam as peças. --}}
@php
    $icons = ['lock-closed', 'shield-check', 'key', 'clipboard-document-list', 'arrow-up-tray', 'archive-box'];
@endphp

<section id="seguranca" class="scroll-mt-24 bg-surface pb-24 sm:pb-32">
    <div class="mx-auto max-w-6xl px-4">
        <div class="v3-rise mx-auto max-w-3xl text-center">
            <h2 class="v3-title text-gray-900 dark:text-gray-50" data-v3-text>{{ __('landing_v3.security.title') }}</h2>
            <p class="v3-lede mx-auto mt-4" data-v3-text>{{ __('landing_v3.security.subtitle') }}</p>
        </div>

        <div class="v3-bento v3-rise mt-14" data-v3-bento>
            @foreach (__('landing_v3.security.items') as $item)
                <article class="v3-cell" data-v3-cell>
                    <span class="v3-glass-tile h-11 w-11">
                        <x-ui-icon :name="$icons[$loop->index]" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $item['title'] }}</h3>
                    <p class="mt-2 max-w-prose text-sm leading-relaxed text-text-muted">{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
