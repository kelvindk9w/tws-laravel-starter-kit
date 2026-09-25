@php
    $repo = platform()->repoUrl;
    $cloneCommand = 'git clone '.$repo.' meu-projeto';
@endphp

{{-- HERÓI — cena PINADA em quatro planos.

     O herói não é uma tela estática com um fade: ele fica preso na viewport
     por cerca de duas telas de rolagem e é o SCROLL que dirige a cena (as
     linhas de log descem, freiam, se alinham e viram o título). O dedo do
     visitante é o cursor da linha do tempo — que é a tese da página inteira.
     A coreografia mora em resources/js/landing-v2/hero-scene.js.

     Os quatro planos, do fundo para a frente (paralaxe crescente):

       ambiente  campo procedural (canvas 2D: manchas lentas + grão)
       rastro    as linhas do log de requisições caindo (canvas 2D)
       objeto    o bloco de log FLUTUANTE, com tilt pelo ponteiro — e é ele
                 que responde a quem chegou: o visitante digita e vê o
                 próprio dado ser redigido
       frente    tipografia e CTAs

     Sem WebGL de propósito: o que se desenha é texto e gradiente de baixa
     frequência, que o canvas 2D faz mais barato — e sem WebGL a CSP da rota
     não precisa de `worker-src` nem de `blob:`.

     Sem mockup de browser: a prova desta página é o log, não uma captura de
     tela dentro de uma moldura desenhada.

     SEM JS a seção inteira já está montada, legível e utilizável — o campo
     interativo é o único que perde a resposta, e ele é enfeite, não conteúdo. --}}
<section
    data-lv2-hero
    class="lv2-hero relative isolate flex min-h-[calc(100svh-4rem)] flex-col justify-center overflow-hidden px-4 py-12"
    aria-labelledby="lv2-hero-heading"
>
    {{-- Plano 0 · ambiente --}}
    <div data-lv2-layer="ambient" class="pointer-events-none absolute inset-0 -z-30" aria-hidden="true">
        <canvas class="lv2-ambient" data-lv2-ambient></canvas>
    </div>

    {{-- Plano 1 · rastro --}}
    <div data-lv2-layer="trace" class="pointer-events-none absolute inset-0 -z-20" aria-hidden="true">
        <canvas class="lv2-canvas" data-lv2-canvas></canvas>
    </div>
    <p class="sr-only">{{ __('landing_v2.hero.canvas_alt') }}</p>

    {{-- Plano 3 · frente --}}
    <div data-lv2-layer="front" class="relative mx-auto w-full max-w-6xl">
        <h1
            id="lv2-hero-heading"
            class="lv2-display mx-auto max-w-[46rem] text-center text-[clamp(2.25rem,8.5vw,5rem)]"
            data-lv2-headline
        >{{ __('landing_v2.hero.title') }}</h1>

        <p
            class="lv2-measure mx-auto mt-6 text-center text-base text-gray-600 sm:text-lg dark:text-gray-300"
            data-lv2-front-item
        >{{ __('landing_v2.hero.subtitle') }}</p>

        {{-- UM primário, UM secundário. O primário é o próprio comando: a
             ação que se pede aqui não é "cadastre-se", é "clone" — e o
             caminho mais curto entre a página e o terminal é o comando já
             copiado. --}}
        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center" data-lv2-front-item>
            <button
                type="button"
                data-lv2-clone
                data-copy="{{ $cloneCommand }}"
                data-copied-text="{{ __('landing_v2.hero.clone_copied') }}"
                class="group inline-flex w-full max-w-full items-center justify-center gap-3 rounded-lg bg-brand px-5 py-3.5 text-brand-foreground transition-colors duration-150 ease-(--ease-out) hover:bg-brand-hover active:scale-[0.99] motion-reduce:active:scale-100 sm:w-auto"
            >
                <span class="shrink-0 text-sm font-semibold">{{ __('landing_v2.hero.clone') }}</span>
                <span class="h-5 w-px shrink-0 bg-current opacity-25" aria-hidden="true"></span>
                <span class="lv2-mono min-w-0 break-all text-start text-[0.6875rem] opacity-90 sm:text-[0.8125rem]">{{ $cloneCommand }}</span>
                <x-ui-icon name="clipboard-document" class="h-4 w-4 shrink-0 opacity-70 transition-opacity duration-150 ease-(--ease-out) group-hover:opacity-100" />
                {{-- Rótulo que o handler [data-copy] do kit troca por
                     "Comando copiado" — invisível, porque a confirmação
                     visível é a linha logo abaixo e o toast do kit. --}}
                <span class="sr-only" data-copy-label>{{ __('landing_v2.hero.clone_copy') }}</span>
            </button>

            <x-button :href="route('login')" variant="secondary" size="lg" class="w-full justify-center sm:w-auto">
                {{ __('landing_v2.hero.demo') }}
            </x-button>
        </div>

        <p
            class="mt-3 text-center text-caption text-text-muted"
            data-lv2-clone-feedback
            aria-live="polite"
            data-lv2-front-item
        >{{ __('landing_v2.hero.clone_copy') }}</p>
    </div>

    {{-- Plano 2 · objeto — o bloco de log flutuante, que É o elemento
         interativo: o visitante digita e vê a redação acontecer no dado
         DELE. Dizer "seus dados são redigidos" é marketing; mostrar o CPF
         da própria pessoa virando 472.***.***-15 enquanto ela digita é
         prova. Nada sai do navegador: o campo não tem `name` e não há
         formulário em volta. --}}
    <div data-lv2-layer="object" class="relative mx-auto mt-10 w-full max-w-2xl">
        <div class="lv2-object-stage" data-lv2-object>
            <div class="lv2-object" data-lv2-object-tilt>
                <div class="flex items-center gap-2 border-b border-border px-4 py-2.5">
                    <x-ui-icon name="command-line" class="h-4 w-4 text-text-muted" />
                    <span class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">POST /register</span>
                </div>

                <div class="p-4 sm:p-5">
                    <label for="lv2-live" class="lv2-mono block text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">
                        {{ __('landing_v2.hero.live_label') }}
                    </label>

                    <input
                        id="lv2-live"
                        type="text"
                        data-lv2-live-input
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="{{ __('landing_v2.hero.live_placeholder') }}"
                        class="lv2-live-input mt-2 w-full border-0 border-b border-border bg-transparent px-0 pb-2 text-sm text-gray-900 placeholder-text-muted focus:border-(--lv2-amber-ink) focus:outline-none focus:ring-0 dark:text-gray-100"
                    >

                    <p class="mt-1.5 text-[0.6875rem] text-text-muted">{{ __('landing_v2.hero.live_hint') }}</p>

                    <div class="mt-4 overflow-x-auto rounded-md border border-border bg-surface-sunken px-3 py-2.5">
                        <p class="lv2-line text-xs">
                            <span class="text-text-muted">payload&nbsp;→&nbsp;</span><span class="lv2-live-output" data-lv2-live-output>{{ __('landing_v2.hero.live_empty') }}</span>
                        </p>
                    </div>

                    <p
                        class="lv2-mono mt-2 min-h-4 text-[0.6875rem] text-(--lv2-amber-ink)"
                        data-lv2-live-note
                        aria-live="polite"
                    ></p>
                </div>
            </div>
        </div>
    </div>

    {{-- A instrução de rolagem mora no PÉ do herói: ali ela é a borda da
         tela, e é a borda da tela que se rola. --}}
    <p
        class="lv2-mono absolute inset-x-0 bottom-4 text-center text-[0.625rem] uppercase tracking-[0.22em] text-text-muted"
        data-lv2-scroll-hint
    >
        {{ __('landing_v2.hero.scroll') }}
    </p>
</section>
