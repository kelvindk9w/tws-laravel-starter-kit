{{-- SEGURANÇA DE FÁBRICA + PRONTO PARA PRODUZIR + A CONTA DAS HORAS.

     Esta é a seção "Recursos" do cabeçalho (#recursos) e a seção "Economia"
     (#horas): as duas âncoras do menu do site moram aqui, na ordem em que a
     objeção aparece — primeiro o que já está feito, depois quanto trabalho
     isso é.

     Uma célula VIVA por vez no bento (resources/js/landing/bento.js): seis
     realces simultâneos seriam ruído; um de cada vez conta que são seis partes
     do MESMO sistema. Sem JS, sem WebGL ou com `prefers-reduced-motion` o
     bento fica inteiro e parado — as bordas já separam as peças. --}}
@php
    $icons = ['lock-closed', 'shield-check', 'key', 'clipboard-document-list', 'arrow-up-tray', 'archive-box'];

    // Os dois recursos que vieram da landing anterior: e-mail transacional
    // pronto (com servidor de dev e pré-visualização) e o fato de painel,
    // admin e e-mails saírem dos MESMOS componentes.
    $readyIcons = ['envelope', 'code-bracket'];

    // ADR-007: o número vem do config, nunca da view. Zero esconde a faixa.
    $hoursSaved = (int) config('landing.hours_saved');
@endphp

<section id="recursos" class="scroll-mt-24 bg-surface pb-24 sm:pb-32">
    <div class="mx-auto max-w-6xl px-4">
        <div class="sky-rise mx-auto max-w-3xl text-center">
            <h2 class="sky-title text-gray-900 dark:text-gray-50" data-sky-text>{{ __('landing.security.title') }}</h2>
            <p class="sky-lede mx-auto mt-4" data-sky-text>{{ __('landing.security.subtitle') }}</p>
        </div>

        <div class="sky-bento sky-rise mt-14" data-sky-bento>
            @foreach (__('landing.security.items') as $item)
                <article class="sky-cell" data-sky-cell>
                    <span class="sky-glass-tile h-11 w-11">
                        <x-ui-icon :name="$icons[$loop->index]" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $item['title'] }}</h3>
                    <p class="mt-2 max-w-prose text-sm leading-relaxed text-text-muted">{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>

        {{-- PRONTO PARA PRODUZIR — linha própria, dois cartões largos. Não
             entraram no bento de segurança porque não são segurança: são o
             que a base entrega ALÉM dela. Misturar os dois assuntos numa
             grade só transformaria "Segurança de fábrica" num rótulo falso. --}}
        <h3 class="sky-rise mt-16 text-center text-sm font-semibold uppercase tracking-[0.14em] text-text-muted">{{ __('landing.ready.title') }}</h3>

        {{-- Grade própria (não `.sky-bento`): o bento de segurança tem uma
             conta de trilhos para SEIS peças. Duas peças ali cairiam em 4+2,
             tortas. Aqui são dois cartões iguais, lado a lado. --}}
        <div class="sky-rise mt-6 grid gap-4 sm:grid-cols-2">
            @foreach (__('landing.ready.items') as $item)
                <article class="sky-cell">
                    <span class="sky-glass-tile h-11 w-11">
                        <x-ui-icon :name="$readyIcons[$loop->index]" class="h-5 w-5" />
                    </span>
                    <h4 class="mt-5 text-lg font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-50">{{ $item['title'] }}</h4>
                    <p class="mt-2 max-w-prose text-sm leading-relaxed text-text-muted">{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>

        {{-- A CONTA — o argumento de quem constrói com IA em uma linha: isto é
             o que não precisa ser gerado, revisado e depurado de novo a cada
             projeto. O número vem do .env (LANDING_HOURS_SAVED); com zero, a
             faixa inteira some — número inventado é pior que número ausente. --}}
        @if ($hoursSaved > 0)
            <div id="horas" class="sky-rise mx-auto mt-16 max-w-3xl scroll-mt-24 text-center">
                <p class="sky-hours">
                    <span class="sky-badge-amber"><span data-sky-count="{{ $hoursSaved }}">{{ number_format($hoursSaved, 0, ',', '.') }}</span>+</span>
                    <span>{{ __('landing.hours.label') }}</span>
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-text-muted">{{ __('landing.hours.caption') }}</p>
            </div>
        @endif
    </div>
</section>
