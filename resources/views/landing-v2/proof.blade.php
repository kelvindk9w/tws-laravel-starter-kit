{{-- PROVA POR INTERAÇÃO — os componentes REAIS do kit, nesta página.

     Nada aqui é captura de tela. O seletor de tema é o <x-theme-toggle> do
     kit; o campo, o interruptor, a etiqueta, o alerta e os botões são os
     mesmos componentes que o painel usa. Quando o tema muda, a PÁGINA INTEIRA
     muda junto — inclusive o fundo do herói e a espinha — porque a /v2
     retona os tokens semânticos, e não pinta cores à mão.

     A confirmação de ação sensível é uma SIMULAÇÃO declarada: o fluxo real
     (senha de transação + código por e-mail → token de uso único) roda no
     servidor e não pode ser disparado de uma landing. Dizer isso na tela é
     parte da prova — uma demo que finge ser produção corrói a confiança que
     a página inteira está tentando construir. --}}
<section id="prova" class="scroll-mt-16 border-b border-border" aria-labelledby="lv2-proof-heading">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <div class="lv2-eyebrowless-rule lv2-reveal"></div>

        {{-- A seção abre pela DOR, não pela feature: o que dá errado
             quando o sistema não se lembra. O título logo abaixo é a
             promessa que responde a ela. --}}
        <p class="lv2-pain mt-8 max-w-3xl lv2-reveal">{{ __('landing_v2.proof.pain') }}</p>

        <div class="mt-5 max-w-3xl">
            <h2 id="lv2-proof-heading" data-lv2-lines class="lv2-display text-[clamp(2rem,5.5vw,3.5rem)]">
                {{ __('landing_v2.proof.heading') }}
            </h2>
            <p class="lv2-measure mt-5 text-gray-600 dark:text-gray-300">
                {{ __('landing_v2.proof.subtitle') }}
            </p>
        </div>

        <div class="mt-14 grid min-w-0 gap-6 lg:grid-cols-2">
            {{-- Tema --}}
            <div class="rounded-xl border border-border bg-surface-raised p-6 lv2-reveal sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-display text-h1 tracking-[-0.02em]">{{ __('landing_v2.proof.theme_title') }}</h3>
                        <p class="lv2-measure mt-3 text-sm text-gray-600 dark:text-gray-300">{{ __('landing_v2.proof.theme_description') }}</p>
                    </div>
                    <x-theme-toggle />
                </div>

                <p class="mt-8 text-caption font-semibold uppercase tracking-widest text-text-muted">{{ __('landing_v2.proof.components_title') }}</p>

                <div class="mt-4 space-y-5 rounded-lg border border-border bg-surface p-5">
                    <x-input
                        :label="__('landing_v2.proof.demo_field')"
                        name="lv2_demo_project"
                        :placeholder="__('landing_v2.proof.demo_field_placeholder')"
                        autocomplete="off"
                    />

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <x-toggle :label="__('landing_v2.proof.demo_toggle')" name="lv2_demo_toggle" checked />
                        <x-badge color="green">{{ __('landing_v2.proof.demo_badge') }}</x-badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-button data-toast-show="lv2-demo-toast">{{ __('landing_v2.proof.demo_button') }}</x-button>
                        <span class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">--color-surface · --color-border</span>
                    </div>
                </div>
            </div>

            {{-- 2FA de mentira, declarado como tal --}}
            <div class="rounded-xl border border-border bg-surface-raised p-6 lv2-reveal sm:p-8" data-lv2-twofa>
                <h3 class="font-display text-h1 tracking-[-0.02em]">{{ __('landing_v2.proof.twofa_title') }}</h3>
                <p class="lv2-measure mt-3 text-sm text-gray-600 dark:text-gray-300">{{ __('landing_v2.proof.twofa_description') }}</p>

                <div class="mt-6 space-y-4">
                    <x-button data-lv2-twofa-send variant="secondary">
                        <x-ui-icon name="envelope" class="h-4 w-4" />
                        <span data-lv2-twofa-send-label>{{ __('landing_v2.proof.twofa_send') }}</span>
                    </x-button>

                    {{-- O alerta é o do kit; o texto muda por JS. aria-live
                         porque o estado do fluxo é a informação principal. --}}
                    <div data-lv2-twofa-feedback aria-live="polite" hidden></div>

                    <div class="flex flex-wrap items-end gap-3" data-lv2-twofa-form hidden>
                        <div class="w-40">
                            <x-input
                                :label="__('landing_v2.proof.twofa_label')"
                                name="lv2_demo_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                placeholder="000000"
                                data-lv2-twofa-input
                            />
                        </div>
                        <x-button data-lv2-twofa-confirm>{{ __('landing_v2.proof.twofa_confirm') }}</x-button>
                        <x-button data-lv2-twofa-reset variant="ghost">{{ __('landing_v2.proof.twofa_reset') }}</x-button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Toasts do kit: o de "Salvar projeto" e o de cópia (#clipboard-toast
         é o id que o handler [data-copy] do ui.js procura na página). --}}
    <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
        <x-toast id="lv2-demo-toast" type="success" class="hidden">{{ __('landing_v2.proof.demo_saved') }}</x-toast>
        <x-toast id="clipboard-toast" type="info" class="hidden">{{ __('landing_v2.depth.copied') }}</x-toast>
    </div>
</section>
