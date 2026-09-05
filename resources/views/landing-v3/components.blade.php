{{-- COMPONENTES — a primeira seção branca. O ambiente acabou no horizonte do
     herói; daqui até o rodapé a página é a superfície do kit, sem cor.

     O argumento é o split-screen: o código que você escreve e a tela que sai
     dele, no mesmo retângulo, com uma linha luminosa entre os dois. O trecho
     à esquerda é o USO REAL do <x-table> no showcase; a imagem à direita é a
     captura daquele mesmo bloco renderizado em /ui. --}}
@php
    $icons = ['squares-2x2', 'computer-desktop', 'cog-6-tooth'];
@endphp

<section id="componentes" class="scroll-mt-24 bg-surface py-24 sm:py-32">
    <div class="mx-auto max-w-6xl px-4">
        <div class="v3-rise mx-auto max-w-3xl text-center">
            <span class="v3-pill v3-pill-quiet">{{ __('landing_v3.components.eyebrow') }}</span>
            <h2 class="v3-title mt-5 text-gray-900 dark:text-gray-50" data-v3-text>{{ __('landing_v3.components.title') }}</h2>
            <p class="v3-lede mx-auto mt-4" data-v3-text>{{ __('landing_v3.components.subtitle') }}</p>
        </div>

        <div
            class="v3-split v3-rise mt-14 aspect-[4/5] sm:aspect-[2/1]"
            data-v3-split
        >
            {{-- Direita (embaixo): a TELA. --}}
            <div class="v3-split-pane v3-split-screen">
                <img
                    src="{{ asset('img/landing-v3/table-light-900.webp') }}"
                    srcset="{{ asset('img/landing-v3/table-light-600.webp') }} 600w, {{ asset('img/landing-v3/table-light-900.webp') }} 900w"
                    sizes="(max-width: 640px) 92vw, 46rem"
                    alt="{{ __('landing_v3.components.screen_alt') }}"
                    width="900"
                    height="245"
                    loading="lazy"
                    decoding="async"
                    class="block w-full rounded-xl shadow-lg shadow-black/5 dark:hidden"
                >
                <img
                    src="{{ asset('img/landing-v3/table-dark-900.webp') }}"
                    srcset="{{ asset('img/landing-v3/table-dark-600.webp') }} 600w, {{ asset('img/landing-v3/table-dark-900.webp') }} 900w"
                    sizes="(max-width: 640px) 92vw, 46rem"
                    alt="{{ __('landing_v3.components.screen_alt') }}"
                    width="900"
                    height="245"
                    loading="lazy"
                    decoding="async"
                    class="hidden w-full rounded-xl shadow-lg shadow-black/40 dark:block"
                >
            </div>

            {{-- Esquerda (por cima, recortada até a linha): o CÓDIGO. --}}
            <div class="v3-split-pane v3-split-code">
                <pre class="v3-split-code-block" data-v3-scroll tabindex="0" aria-label="{{ __('landing_v3.components.code_label') }}"><code>@verbatim<span class="v3-tok-cmt">{{-- resources/views/showcase.blade.php --}}</span>
<span class="v3-tok-tag">&lt;x-table</span> <span class="v3-tok-attr">:headers</span>=<span class="v3-tok-str">"[__('…name'), __('…status'), '']"</span><span class="v3-tok-tag">&gt;</span>
    <span class="v3-tok-tag">@foreach</span> ($rows <span class="v3-tok-attr">as</span> [$name, $color])
        <span class="v3-tok-tag">&lt;x-table-row&gt;</span>
            <span class="v3-tok-tag">&lt;x-table-cell</span> <span class="v3-tok-attr">:label</span>=<span class="v3-tok-str">"__('…name')"</span><span class="v3-tok-tag">&gt;</span>
                {{ $name }}
            <span class="v3-tok-tag">&lt;/x-table-cell&gt;</span>
            <span class="v3-tok-tag">&lt;x-table-cell</span> <span class="v3-tok-attr">:label</span>=<span class="v3-tok-str">"__('…status')"</span><span class="v3-tok-tag">&gt;</span>
                <span class="v3-tok-tag">&lt;x-badge</span> <span class="v3-tok-attr">:color</span>=<span class="v3-tok-str">"$color"</span><span class="v3-tok-tag">&gt;</span>{{ $label }}<span class="v3-tok-tag">&lt;/x-badge&gt;</span>
            <span class="v3-tok-tag">&lt;/x-table-cell&gt;</span>
            <span class="v3-tok-tag">&lt;x-table-cell</span> <span class="v3-tok-attr">align</span>=<span class="v3-tok-str">"end"</span><span class="v3-tok-tag">&gt;</span>…<span class="v3-tok-tag">&lt;/x-table-cell&gt;</span>
        <span class="v3-tok-tag">&lt;/x-table-row&gt;</span>
    <span class="v3-tok-tag">@endforeach</span>
<span class="v3-tok-tag">&lt;/x-table&gt;</span>
@endverbatim</code></pre>
            </div>

            <span class="v3-split-tag v3-pill v3-pill-quiet left-4">{{ __('landing_v3.components.code_label') }}</span>
            <span class="v3-split-tag v3-pill v3-pill-quiet right-4">{{ __('landing_v3.components.screen_label') }}</span>

            <span class="v3-split-line" aria-hidden="true"></span>
            <span class="v3-split-grip v3-pill v3-pill-glass" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 6 5 12l4 6M15 6l4 6-4 6" />
                </svg>
                {{ __('landing_v3.components.drag_hint') }}
            </span>

            {{-- O controle de verdade: range com rótulo, operável no teclado. --}}
            <input
                type="range"
                class="v3-split-range"
                min="8"
                max="92"
                step="1"
                value="52"
                aria-label="{{ __('landing_v3.components.slider_label') }}"
                data-v3-split-range
            >
        </div>

        <div class="mt-16 grid gap-10 sm:grid-cols-3">
            @foreach (__('landing_v3.components.items') as $item)
                <div class="v3-rise" style="--v3-delay: {{ $loop->index * 80 }}ms">
                    <span class="v3-glass-tile h-12 w-12">
                        <x-ui-icon :name="$icons[$loop->index]" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-text-muted">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
