@php
    /**
     * Realce mínimo de sintaxe, feito no servidor: comentário e literal de
     * string. Nada de biblioteca de highlight no bundle — dois tons já dão
     * a hierarquia que o olho precisa, e o resto é a mono fazendo o trabalho.
     * Escapa PRIMEIRO e só então marca, para que o código exibido não possa
     * injetar HTML.
     */
    $highlight = static function (string $code): string {
        $lines = explode("\n", $code);

        foreach ($lines as $i => $line) {
            $escaped = e($line);

            if (preg_match('/^\s*(#|\/\/)/', $line) === 1) {
                $lines[$i] = '<span class="tok-comment">'.$escaped.'</span>';

                continue;
            }

            $lines[$i] = (string) preg_replace(
                '/(&#039;[^&]*?&#039;|&quot;[^&]*?&quot;)/',
                '<span class="tok-string">$1</span>',
                $escaped,
            );
        }

        return implode("\n", $lines);
    };
@endphp

{{-- PROFUNDIDADE TÉCNICA — bento com UMA célula viva por vez.

     A célula ativa acende (fio âmbar de 1px no topo, superfície elevada) e
     alimenta UM painel de código abaixo. O código não abre dentro da célula
     de propósito: expandir uma célula empurra as outras cinco e a página
     salta — a informação chega, mas o layout mente. Um painel fixo troca de
     conteúdo sem mover nada (CLS ~0) e cabe no mesmo gesto.

     Teclado: padrão tablist do WAI-ARIA — setas percorrem, Home/End vão às
     pontas, e só a célula ativa fica na ordem de tabulação. --}}
<section id="modulos" class="scroll-mt-16 border-b border-border" aria-labelledby="lv2-depth-heading">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <div class="lv2-eyebrowless-rule lv2-reveal"></div>

        {{-- A seção abre pela DOR, não pela feature: o que dá errado
             quando o sistema não se lembra. O título logo abaixo é a
             promessa que responde a ela. --}}
        <p class="lv2-pain mt-8 max-w-3xl lv2-reveal">{{ __('landing_v2.depth.pain') }}</p>

        <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-3xl">
                <h2 id="lv2-depth-heading" data-lv2-lines class="lv2-display text-[clamp(2rem,5.5vw,3.5rem)]">
                    {{ __('landing_v2.depth.heading') }}
                </h2>
                <p class="lv2-measure mt-5 text-gray-600 dark:text-gray-300">
                    {{ __('landing_v2.depth.subtitle') }}
                </p>
            </div>
            <p class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">
                {{ __('landing_v2.depth.hint') }}
            </p>
        </div>

        <div
            role="tablist"
            aria-label="{{ __('landing_v2.depth.heading') }}"
            class="mt-12 grid min-w-0 gap-px border border-border bg-border sm:grid-cols-2 lg:grid-cols-4"
            data-lv2-bento
        >
            @foreach ($cells as $index => $cell)
                <button
                    type="button"
                    role="tab"
                    id="lv2-cell-{{ $cell['key'] }}"
                    aria-controls="lv2-panel-{{ $cell['key'] }}"
                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                    tabindex="{{ $index === 0 ? '0' : '-1' }}"
                    class="lv2-cell {{ $cell['span'] }} min-w-0 p-6 sm:p-7"
                >
                    <span class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">{{ $cell['lang'] }}</span>
                    <span class="mt-3 block font-display text-h2 text-gray-900 dark:text-gray-100">
                        {{ __('landing_v2.depth.cells.'.$cell['key'].'.title') }}
                    </span>
                    <span class="mt-2 block text-caption text-text-muted">
                        {{ __('landing_v2.depth.cells.'.$cell['key'].'.description') }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="border border-t-0 border-border bg-surface-raised">
            @foreach ($cells as $index => $cell)
                <div
                    role="tabpanel"
                    id="lv2-panel-{{ $cell['key'] }}"
                    aria-labelledby="lv2-cell-{{ $cell['key'] }}"
                    tabindex="0"
                    @if ($index !== 0) hidden @endif
                    data-lv2-panel
                >
                    <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-3">
                        <p class="lv2-mono text-[0.6875rem] uppercase tracking-[0.16em] text-text-muted">
                            {{ __('landing_v2.depth.cells.'.$cell['key'].'.title') }}
                        </p>
                        <button
                            type="button"
                            data-copy="{{ $cell['code'] }}"
                            data-copied-text="{{ __('landing_v2.depth.copied') }}"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-caption text-text-muted transition-colors duration-150 ease-(--ease-out) hover:border-border-strong hover:bg-surface-sunken hover:text-gray-900 dark:hover:text-gray-100"
                        >
                            <x-ui-icon name="clipboard-document" class="h-3.5 w-3.5" />
                            <span data-copy-label>{{ __('landing_v2.depth.copy') }}</span>
                        </button>
                    </div>
                    <pre class="lv2-code px-5 py-5 sm:px-6"><code>{!! $highlight($cell['code']) !!}</code></pre>
                </div>
            @endforeach
        </div>
    </div>
</section>
