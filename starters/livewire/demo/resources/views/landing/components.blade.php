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
        <div class="sky-rise mx-auto max-w-3xl text-center">
            <span class="sky-pill sky-pill-quiet">{{ __('landing.components.eyebrow') }}</span>
            <h2 class="sky-title mt-5 text-gray-900 dark:text-gray-50" data-sky-text>{{ __('landing.components.title') }}</h2>
            <p class="sky-lede mx-auto mt-4" data-sky-text>{{ __('landing.components.subtitle') }}</p>
        </div>

        <div
            class="sky-split sky-rise mt-14 aspect-[4/5] sm:aspect-[2/1]"
            data-sky-split
        >
            {{-- Direita (embaixo): a TELA. --}}
            <div class="sky-split-pane sky-split-screen">
                <img
                    src="{{ asset('img/landing/table-light-900.webp') }}"
                    srcset="{{ asset('img/landing/table-light-600.webp') }} 600w, {{ asset('img/landing/table-light-900.webp') }} 900w"
                    sizes="(max-width: 640px) 92vw, 46rem"
                    alt="{{ __('landing.components.screen_alt') }}"
                    width="900"
                    height="245"
                    loading="lazy"
                    decoding="async"
                    class="block w-full rounded-xl shadow-lg shadow-black/5 dark:hidden"
                >
                <img
                    src="{{ asset('img/landing/table-dark-900.webp') }}"
                    srcset="{{ asset('img/landing/table-dark-600.webp') }} 600w, {{ asset('img/landing/table-dark-900.webp') }} 900w"
                    sizes="(max-width: 640px) 92vw, 46rem"
                    alt="{{ __('landing.components.screen_alt') }}"
                    width="900"
                    height="245"
                    loading="lazy"
                    decoding="async"
                    class="hidden w-full rounded-xl shadow-lg shadow-black/40 dark:block"
                >
            </div>

            {{-- Esquerda (por cima, recortada até a linha): o CÓDIGO. --}}
            <div class="sky-split-pane sky-split-code">
                <pre class="sky-split-code-block" data-sky-scroll tabindex="0" aria-label="{{ __('landing.components.code_label') }}"><code>@verbatim<span class="sky-tok-cmt">{{-- resources/views/showcase.blade.php --}}</span>
<span class="sky-tok-tag">&lt;x-table</span> <span class="sky-tok-attr">:headers</span>=<span class="sky-tok-str">"[__('…name'), __('…status'), '']"</span><span class="sky-tok-tag">&gt;</span>
    <span class="sky-tok-tag">@foreach</span> ($rows <span class="sky-tok-attr">as</span> [$name, $color])
        <span class="sky-tok-tag">&lt;x-table-row&gt;</span>
            <span class="sky-tok-tag">&lt;x-table-cell</span> <span class="sky-tok-attr">:label</span>=<span class="sky-tok-str">"__('…name')"</span><span class="sky-tok-tag">&gt;</span>
                {{ $name }}
            <span class="sky-tok-tag">&lt;/x-table-cell&gt;</span>
            <span class="sky-tok-tag">&lt;x-table-cell</span> <span class="sky-tok-attr">:label</span>=<span class="sky-tok-str">"__('…status')"</span><span class="sky-tok-tag">&gt;</span>
                <span class="sky-tok-tag">&lt;x-badge</span> <span class="sky-tok-attr">:color</span>=<span class="sky-tok-str">"$color"</span><span class="sky-tok-tag">&gt;</span>{{ $label }}<span class="sky-tok-tag">&lt;/x-badge&gt;</span>
            <span class="sky-tok-tag">&lt;/x-table-cell&gt;</span>
            <span class="sky-tok-tag">&lt;x-table-cell</span> <span class="sky-tok-attr">align</span>=<span class="sky-tok-str">"end"</span><span class="sky-tok-tag">&gt;</span>…<span class="sky-tok-tag">&lt;/x-table-cell&gt;</span>
        <span class="sky-tok-tag">&lt;/x-table-row&gt;</span>
    <span class="sky-tok-tag">@endforeach</span>
<span class="sky-tok-tag">&lt;/x-table&gt;</span>
@endverbatim</code></pre>
            </div>

            <span class="sky-split-tag sky-pill sky-pill-quiet left-4">{{ __('landing.components.code_label') }}</span>
            <span class="sky-split-tag sky-pill sky-pill-quiet right-4">{{ __('landing.components.screen_label') }}</span>

            <span class="sky-split-line" aria-hidden="true"></span>
            <span class="sky-split-grip sky-pill sky-pill-glass" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 6 5 12l4 6M15 6l4 6-4 6" />
                </svg>
                {{ __('landing.components.drag_hint') }}
            </span>

            {{-- O controle de verdade: range com rótulo, operável no teclado. --}}
            <input
                type="range"
                class="sky-split-range"
                min="8"
                max="92"
                step="1"
                value="52"
                aria-label="{{ __('landing.components.slider_label') }}"
                data-sky-split-range
            >
        </div>

        <div class="mt-16 grid gap-10 sm:grid-cols-3">
            @foreach (__('landing.components.items') as $item)
                <div class="sky-rise" style="--sky-delay: {{ $loop->index * 80 }}ms">
                    <span class="sky-glass-tile h-12 w-12">
                        <x-ui-icon :name="$icons[$loop->index]" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-text-muted">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
