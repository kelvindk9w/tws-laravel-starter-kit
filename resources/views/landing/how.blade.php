{{-- COMO FUNCIONA — três cartões cinza-claro com uma colagem do produto e uma
     ETIQUETA DE CURSOR por etapa.

     A etiqueta é o gesto emprestado da referência: um balão com a setinha do
     ponteiro, como se alguém estivesse mostrando a tela ao vivo. Aqui ela tem
     função — nomeia a etapa dentro da imagem, no lugar em que a etapa
     acontece, em vez de numerar cartões de fora. --}}
@php
    // Cada etapa mostra o pedaço do produto que ela produz.
    $collages = ['ui', 'login', 'dashboard'];

    // Posição da etiqueta dentro da colagem (a mão de quem aponta muda de
    // lugar; três etiquetas no mesmo canto seriam três carimbos).
    $spots = ['left-5 top-6', 'right-5 top-10', 'left-8 bottom-8'];
@endphp

<section id="como-funciona" class="scroll-mt-24 bg-surface pb-24 sm:pb-32">
    <div class="mx-auto max-w-6xl px-4">
        <div class="sky-rise mx-auto max-w-3xl text-center">
            <h2 class="sky-title text-gray-900 dark:text-gray-50" data-sky-text>{{ __('landing.how.title') }}</h2>
            <p class="sky-lede mx-auto mt-4" data-sky-text>{{ __('landing.how.subtitle') }}</p>
        </div>

        <ol class="mt-14 grid gap-6 md:grid-cols-3">
            @foreach (__('landing.how.steps') as $step)
                @php $collage = $collages[$loop->index]; @endphp
                <li class="sky-step sky-rise" style="--sky-delay: {{ $loop->index * 90 }}ms">
                    <div class="sky-step-collage">
                        <img
                            src="{{ asset("img/landing/{$collage}-light-720.webp") }}"
                            alt=""
                            width="720"
                            height="450"
                            loading="lazy"
                            decoding="async"
                            class="block dark:hidden"
                        >
                        <img
                            src="{{ asset("img/landing/{$collage}-dark-720.webp") }}"
                            alt=""
                            width="720"
                            height="450"
                            loading="lazy"
                            decoding="async"
                            class="hidden dark:block"
                        >

                        {{-- Etiqueta de cursor. Decorativa por dentro (a seta),
                             mas o TEXTO dela é conteúdo: repete o verbo da
                             etapa em minúscula, como um comando. --}}
                        <span class="sky-cursor {{ $spots[$loop->index] }}">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M5.5 3.2 18.6 11a.6.6 0 0 1-.1 1.1l-5.2 1.5-2.4 5a.6.6 0 0 1-1.1-.1L5.5 3.2z" />
                            </svg>
                            {{ $step['cursor'] }}
                        </span>
                    </div>

                    <div class="p-6">
                        <h3 class="text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-text-muted">{{ $step['text'] }}</p>
                        {{-- Monoespaçada porque É um comando de terminal —
                             não é "cara de técnico", é o texto que se copia. --}}
                        <code class="mt-4 block overflow-x-auto rounded-lg border border-border bg-surface px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300" data-sky-scroll>{{ $step['command'] }}</code>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
