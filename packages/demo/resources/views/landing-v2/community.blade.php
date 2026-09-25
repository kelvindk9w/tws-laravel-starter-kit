{{-- COMUNIDADE — o repositório e a contagem de estrelas.

     A contagem vem de um fetch client-side na API pública do GitHub, com
     FALLBACK SILENCIOSO: se a chamada falhar (offline, rate limit, CSP de um
     ambiente mais fechado), o bloco simplesmente não mostra número nenhum —
     nunca um "0", nunca um erro. Um número que pode mentir é pior do que a
     ausência dele.

     É a única chamada externa da página: por isso a CSP da /v2 acrescenta
     api.github.com ao connect-src, e SÓ isso (ver config/security.php). --}}
<section id="repositorio" class="scroll-mt-16 border-b border-border" aria-labelledby="lv2-community-heading">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <div class="lv2-eyebrowless-rule lv2-reveal"></div>

        {{-- A seção abre pela DOR, não pela feature: o que dá errado
             quando o sistema não se lembra. O título logo abaixo é a
             promessa que responde a ela. --}}
        <p class="lv2-pain mt-8 max-w-3xl lv2-reveal">{{ __('landing_v2.community.pain') }}</p>

        <div class="mt-5 grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:items-end lg:gap-16">
            <div>
                <h2 id="lv2-community-heading" data-lv2-lines class="lv2-display text-[clamp(2rem,5.5vw,3.5rem)]">
                    {{ __('landing_v2.community.heading') }}
                </h2>
                <p class="lv2-measure mt-5 text-gray-600 dark:text-gray-300">
                    {{ __('landing_v2.community.subtitle') }}
                </p>
            </div>

            <div class="flex flex-col items-start gap-6 lg:items-end">
                {{-- Estrelas: escondido até existir um número. --}}
                <p class="flex items-baseline gap-3" data-lv2-stars hidden>
                    <span class="lv2-display text-[clamp(2.25rem,6vw,3.5rem)] tabular-nums" data-lv2-stars-value>—</span>
                    <span class="text-caption text-text-muted">{{ __('landing_v2.community.stars') }}</span>
                </p>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <a
                        href="{{ platform()->repoUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 text-sm text-gray-900 underline decoration-border-strong underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:decoration-current dark:text-gray-100"
                    >
                        {{ __('landing_v2.community.repo') }}
                        <x-ui-icon name="arrow-top-right-on-square" class="h-4 w-4" />
                    </a>

                    <p class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">
                        {{ __('landing_v2.community.license_note', ['license' => $stats['license']]) }}
                        ·
                        {{ __('landing_v2.community.version_note', ['version' => $stats['version']]) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
