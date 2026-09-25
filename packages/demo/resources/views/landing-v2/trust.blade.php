{{-- FAIXA DE CONFIANÇA — uma linha, números grandes e VERIFICÁVEIS.

     Nenhum deles é digitado no Blade: a contagem de testes vem da varredura
     da suíte Pest real, a licença do composer.json, a versão de
     config/platform.php (.env) e a data do build do manifest do Vite. Um
     número de landing que ninguém pode conferir é decoração.

     O "+" só entra no que é CONTAGEM e só cresce: testes e arquivos de teste
     hoje são o piso, não o teto. Licença e data não levam "+" nem contador —
     uma data que sobe de zero não é animação, é mentira animada.

     Sem "big number + rótulo + acento" repetido em cartões iguais: é uma
     pauta só, com fios de 1px separando as colunas — o formato de um
     cabeçalho de livro-razão. --}}
<section class="border-y border-border" aria-labelledby="lv2-trust-heading">
    <h2 id="lv2-trust-heading" class="sr-only">{{ __('landing_v2.trust.heading') }}</h2>

    @php
        $figures = [
            ['value' => $stats['tests'], 'count' => true, 'label' => __('landing_v2.trust.tests')],
            ['value' => $stats['test_files'], 'count' => true, 'label' => __('landing_v2.trust.files')],
            ['value' => $stats['license'], 'count' => false, 'label' => __('landing_v2.trust.license')],
            $stats['build'] !== null
                ? ['value' => $stats['build'], 'count' => false, 'label' => __('landing_v2.trust.build')]
                : ['value' => 'v'.$stats['version'], 'count' => false, 'label' => __('landing_v2.trust.version')],
        ];
    @endphp

    <div class="mx-auto grid max-w-6xl grid-cols-2 divide-x divide-y divide-border px-0 lv2-reveal sm:grid-cols-4 sm:divide-y-0">
        @foreach ($figures as $figure)
            <div class="px-5 py-8 sm:px-8 sm:py-10">
                <p
                    class="lv2-display text-[clamp(1.75rem,4.5vw,2.75rem)] tabular-nums"
                    @if ($figure['count'])
                        data-lv2-count="{{ $figure['value'] }}"
                        data-lv2-count-suffix="+"
                    @endif
                >{{ $figure['count'] ? __('landing_v2.trust.plus', ['value' => number_format((int) $figure['value'], 0, ',', '.')]) : $figure['value'] }}</p>
                <p class="mt-2 text-caption text-text-muted">{{ $figure['label'] }}</p>
            </div>
        @endforeach
    </div>
</section>
