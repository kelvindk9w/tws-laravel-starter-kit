{{-- A TESE DE SEGURANÇA — e o MOMENTO-ASSINATURA da página.

     O visitante passa o cursor (ou toca) numa requisição com dado sensível e
     a redação LGPD acontece na frente dele: uma varredura âmbar cobre o valor
     da esquerda para a direita e, atrás dela, o valor volta JÁ REDIGIDO.

     O que aparece não é uma imitação: os payloads são mocados, mas passaram
     pelo App\Core\Logging\Redactor DE VERDADE no controller — é a saída da
     classe que roda em produção. Por isso a legenda consegue dizer QUAL regra
     agiu em cada campo: quem decidiu foi o código, não o designer.

     Acessibilidade: cada requisição é um group focalizável (Tab), a redação
     dispara também no foco e o resultado é anunciado por aria-live. Um efeito
     que só existe para quem tem mouse não é um efeito, é um privilégio. --}}
<section id="redacao" class="scroll-mt-16 border-b border-border" aria-labelledby="lv2-thesis-heading">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <div class="lv2-eyebrowless-rule lv2-reveal"></div>

        {{-- A seção abre pela DOR, não pela feature: o que dá errado
             quando o sistema não se lembra. O título logo abaixo é a
             promessa que responde a ela. --}}
        <p class="lv2-pain mt-8 max-w-3xl lv2-reveal">{{ __('landing_v2.thesis.pain') }}</p>

        <div class="mt-5 max-w-3xl">
            <h2 id="lv2-thesis-heading" data-lv2-lines class="lv2-display text-[clamp(2rem,5.5vw,3.5rem)]">
                {{ __('landing_v2.thesis.heading') }}
            </h2>
            <p class="lv2-measure mt-5 text-gray-600 dark:text-gray-300">
                {{ __('landing_v2.thesis.subtitle') }}
            </p>
        </div>

        <div class="mt-14 space-y-5">
            @foreach ($auditedPayloads as $index => $audit)
                <article
                    class="lv2-audit rounded-xl lv2-reveal"
                    tabindex="0"
                    role="group"
                    aria-label="{{ $audit['method'] }} {{ $audit['endpoint'] }}"
                    data-lv2-audit="{{ $index }}"
                >
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3">
                        <p class="lv2-mono min-w-0 truncate text-xs">
                            <span class="text-text-muted">{{ $audit['method'] }}</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $audit['endpoint'] }}</span>
                        </p>

                        <p class="lv2-status lv2-status-warn shrink-0" data-lv2-audit-state aria-live="polite">
                            <span class="lv2-mono normal-case tracking-normal text-text-muted" data-lv2-audit-hint>
                                <span class="hidden sm:inline">{{ __('landing_v2.thesis.hint_pointer') }}</span>
                                <span class="sm:hidden">{{ __('landing_v2.thesis.hint_touch') }}</span>
                            </span>
                        </p>
                    </header>

                    <div class="overflow-x-auto px-5 py-5">
                        {{-- O payload é escrito como JSON de verdade, uma
                             linha por campo. A indentação vem do padding, não
                             de espaços no fonte: com `white-space: pre` no
                             bloco inteiro, o recuo do próprio Blade viraria
                             parte do texto renderizado. --}}
                        <div class="lv2-json">
                            <p class="text-text-muted">{</p>
                            @foreach ($audit['fields'] as $fieldIndex => $field)
                                <p
                                    class="lv2-field ps-6"
                                    data-lv2-field
                                    data-raw="{{ $field['raw'] }}"
                                    data-redacted="{{ $field['redacted'] }}"
                                    data-rule="{{ $field['rule'] }}"
                                ><span class="text-gray-700 dark:text-gray-300">"{{ $field['key'] }}"</span><span class="text-text-muted">:&nbsp;</span><span class="lv2-field-value text-gray-900 dark:text-gray-100" data-lv2-value>"{{ $field['raw'] }}"<span class="lv2-sweep" aria-hidden="true"></span></span><span class="text-text-muted">{{ $fieldIndex === count($audit['fields']) - 1 ? '' : ',' }}</span>@if ($field['rule'] !== 'none')<span class="lv2-rule-note ms-3 text-[0.6875rem] text-text-muted">← {{ __('landing_v2.thesis.rules.'.$field['rule']) }}</span>@endif</p>
                            @endforeach
                            <p class="text-text-muted">}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="mt-6 text-caption text-text-muted lv2-reveal">
            <button type="button" data-lv2-audit-reset class="underline decoration-border-strong underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-100">
                {{ __('landing_v2.thesis.reset') }}
            </button>
        </p>

        {{-- A cadeia: INICIADA nasce no recebimento e só sai daí por
             transição controlada. Os conectores são fios de 1px que o GSAP
             desenha (scaleX/scaleY a partir de 0) quando a seção entra. --}}
        <div class="mt-24 grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:gap-16" data-lv2-chain>
            <div class="lv2-reveal">
                <h3 data-lv2-lines class="lv2-display text-[clamp(1.75rem,4vw,2.75rem)]">{{ __('landing_v2.thesis.chain_heading') }}</h3>
                <p class="lv2-measure mt-5 text-sm text-gray-600 dark:text-gray-300">{{ __('landing_v2.thesis.chain_subtitle') }}</p>
            </div>

            <ol class="relative min-w-0">
                @foreach ($chain as $index => $node)
                    @php $isRoot = $index === 0; @endphp
                    <li class="relative flex gap-5 pb-8 last:pb-0 lv2-reveal">
                        {{-- Trilho vertical + nó. O primeiro é o tronco
                             (INICIADA); os três seguintes são os destinos. --}}
                        <div class="relative flex w-4 shrink-0 flex-col items-center">
                            <span class="mt-1.5 block h-3 w-3 shrink-0 rounded-full border {{ $isRoot ? 'border-(--lv2-amber) bg-(--lv2-amber)' : 'border-border-strong bg-surface' }}"></span>
                            @unless ($loop->last)
                                <span
                                    class="mt-1 w-px flex-1 origin-top bg-border-strong"
                                    data-lv2-chain-rail
                                    aria-hidden="true"
                                ></span>
                            @endunless
                        </div>

                        <div class="min-w-0 pb-1">
                            <p class="lv2-status {{ $isRoot ? 'lv2-status-warn' : 'lv2-status-ok' }}">
                                {{ $node['status'] }}@if ($node['http'] !== null) <span class="text-text-muted">· HTTP {{ $node['http'] }}</span>@endif
                            </p>
                            <p class="lv2-measure mt-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ __('landing_v2.thesis.chain.'.$node['status']) }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
