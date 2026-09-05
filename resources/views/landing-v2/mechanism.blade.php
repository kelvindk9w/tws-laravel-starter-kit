{{-- MECANISMO — três passos, e o terminal digita os comandos REAIS.

     A coreografia tem timing humano de propósito: o ritmo de digitação varia
     por caractere, para depois de um espaço e respira antes do Enter. Um
     terminal que "digita" em velocidade constante é uma barra de progresso
     fingindo ser uma pessoa — e todo mundo percebe.

     Os comandos vêm do LandingV2Controller (os mesmos do README, com a URL do
     repositório do .env). Cada um é copiável: quem já entendeu não precisa
     esperar a animação terminar. --}}
<section id="mecanismo" class="scroll-mt-16 border-b border-border" aria-labelledby="lv2-mechanism-heading">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <div class="lv2-eyebrowless-rule lv2-reveal"></div>

        {{-- A seção abre pela DOR, não pela feature: o que dá errado
             quando o sistema não se lembra. O título logo abaixo é a
             promessa que responde a ela. --}}
        <p class="lv2-pain mt-8 max-w-3xl lv2-reveal">{{ __('landing_v2.mechanism.pain') }}</p>

        <div class="mt-5 max-w-3xl">
            <h2 id="lv2-mechanism-heading" data-lv2-lines class="lv2-display text-[clamp(2rem,5.5vw,3.5rem)]">
                {{ __('landing_v2.mechanism.heading') }}
            </h2>
            <p class="lv2-measure mt-5 text-gray-600 dark:text-gray-300">
                {{ __('landing_v2.mechanism.subtitle') }}
            </p>
        </div>

        <div class="mt-14 grid gap-10 lg:grid-cols-[1fr_1.15fr] lg:gap-16" data-lv2-mechanism>
            {{-- Terminal: primeiro no DOM (no mobile é o que se quer ver);
                 na coluna 2 e grudado no topo a partir de lg. --}}
            <div class="min-w-0 lg:order-2">
                <div class="lv2-terminal min-w-0 rounded-xl lg:sticky lg:top-24">
                    <div class="flex items-center gap-2 border-b border-border px-4 py-2.5">
                        <x-ui-icon name="command-line" class="h-4 w-4 text-text-muted" />
                        <span class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">bash</span>
                    </div>

                    <div class="min-h-[13rem] overflow-x-auto p-4 sm:min-h-[15rem] sm:p-5" data-lv2-terminal-body>
                        {{-- Sem JS a página continua útil: os três comandos
                             já estão escritos aqui, na ordem. O JS apenas
                             assume o bloco e os digita. --}}
                        @foreach ($steps as $step)
                            <p class="lv2-line text-gray-700 dark:text-gray-300"><span class="text-text-muted">$ </span>{{ $step['command'] }}</p>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Passos. A numeração fica porque AQUI a sequência é a
                 informação: são três comandos numa ordem que não se inverte. --}}
            <ol class="min-w-0 lg:order-1">
                @foreach (__('landing_v2.mechanism.steps') as $index => $step)
                    <li
                        class="lv2-step border-t border-border py-8 lv2-reveal last:border-b"
                        data-lv2-step="{{ $index }}"
                    >
                        <p class="lv2-step-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="mt-3 font-display text-h1 tracking-[-0.02em]">{{ $step['title'] }}</h3>
                        <p class="lv2-measure mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $step['description'] }}</p>

                        <button
                            type="button"
                            data-copy="{{ $steps[$index]['command'] }}"
                            data-copied-text="{{ __('landing_v2.depth.copied') }}"
                            class="mt-4 inline-flex max-w-full items-center gap-2 rounded-md border border-border px-3 py-2 text-start transition-colors duration-150 ease-(--ease-out) hover:border-border-strong hover:bg-surface-sunken"
                        >
                            <x-ui-icon name="clipboard-document" class="h-3.5 w-3.5 shrink-0 text-text-muted" />
                            <code class="lv2-mono min-w-0 break-all text-xs text-gray-700 dark:text-gray-300">{{ $steps[$index]['command'] }}</code>
                            <span class="sr-only" data-copy-label>{{ __('landing_v2.depth.copy') }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
