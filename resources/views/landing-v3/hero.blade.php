{{-- HERÓI — a cena. É a única seção com ambiente (junto do rodapé) e a única
     que o scroll DIRIGE: enquanto ela está pinada, a página não sobe, a cena
     se abre (resources/js/landing-v3/motion.js).

     TRÊS PLANOS, três velocidades — é a profundidade que separa uma página
     construída de um template:
       back  céu, nuvens e grão   (quase parado: está longe)
       mid   leque de telas e os objetos 3D
       front tipografia, pílulas e CTAs (sai de cena primeiro)

     A ordem de leitura é a ordem do DOM: prova → dor → promessa → ação →
     produto. Os objetos 3D vivem NAS BORDAS, atrás do texto: o assunto do
     herói é a frase, não o vidro. --}}
@php
    $clones = (int) config('landing_v3.clones');
    $tests = (int) config('landing_v3.tests');
    $cloneUrl = platform()->repoUrl ?? route('register');

    // O número da prova social: quantos clonaram, quando o .env traz esse dado;
    // senão, a suíte verde — o fato que o kit tem para mostrar hoje.
    $proofCount = $clones > 0 ? $clones : $tests;

    // Quatro telas REAIS, capturadas em 1440×900 (public/img/landing-v3).
    // A do meio é a do produto (o painel) e fica por cima no eixo z.
    // Inclinação do leque: as pontas abrem em ±14°, as internas em ±8°, e a
    // carta do PRODUTO (o painel) fica reta e à frente — é ela que o visitante
    // precisa enxergar inteira.
    $screens = [
        ['key' => 'ui', 'angle' => -14, 'lift' => 34],
        ['key' => 'admin', 'angle' => -8, 'lift' => 14],
        ['key' => 'dashboard', 'angle' => 0, 'lift' => 0],
        ['key' => 'login', 'angle' => 11, 'lift' => 26],
    ];
@endphp

<section class="v3-sky relative flex min-h-[44rem] flex-col justify-center overflow-hidden pb-10 pt-24 md:h-[100svh]" data-v3-hero>
    {{-- PLANO DE FUNDO — o ambiente é gerado, não é imagem: gradiente de céu,
         grade mascarada, três volumes de nuvem em deriva lenta e um grão fino
         por cima. Nenhum asset, nenhuma requisição. --}}
    <div class="v3-clouds" aria-hidden="true" data-v3-layer="back">
        <span class="v3-cloud v3-cloud-1"></span>
        <span class="v3-cloud v3-cloud-2"></span>
        <span class="v3-cloud v3-cloud-3"></span>
    </div>
    <div class="v3-grain" aria-hidden="true"></div>

    {{-- PLANO DO MEIO (1/2) — objetos de vidro. Bloco decorativo inteiro: quem
         usa leitor de tela não perde nada ao pulá-lo. O 3D só substitui o
         fallback DEPOIS de montar (data-v3-3d-active) — se o WebGL falhar, os
         ladrilhos de CSS continuam ali. --}}
    <div class="pointer-events-none absolute inset-0 hidden md:block" aria-hidden="true" data-v3-3d-scope data-v3-layer="mid">
        <div class="v3-canvas" data-v3-3d data-v3-layout="float" data-v3-marks="#v3-hero-marks"></div>

        <div id="v3-hero-marks" class="absolute inset-0" data-v3-3d-fallback>
            <span class="absolute left-[4%] top-[22%] block">@include('landing-v3.tech-mark', ['name' => 'laravel', 'cube' => true, 'cubeClass' => 'h-20 w-20', 'class' => 'h-9 w-9', 'tilt' => '-14deg'])</span>
            <span class="absolute right-[5%] top-[16%] block">@include('landing-v3.tech-mark', ['name' => 'docker', 'cube' => true, 'cubeClass' => 'h-16 w-16', 'class' => 'h-7 w-7', 'tilt' => '12deg'])</span>
            <span class="absolute left-[7%] top-[62%] block">@include('landing-v3.tech-mark', ['name' => 'redis', 'cube' => true, 'cubeClass' => 'h-16 w-16', 'class' => 'h-7 w-7', 'tilt' => '8deg'])</span>
            <span class="absolute right-[4%] top-[66%] block">@include('landing-v3.tech-mark', ['name' => 'tailwind', 'cube' => true, 'cubeClass' => 'h-20 w-20', 'class' => 'h-9 w-9', 'tilt' => '-16deg'])</span>
        </div>
    </div>

    {{-- PLANO DA FRENTE — a promessa e a ação. --}}
    <div class="relative z-10 mx-auto max-w-6xl px-4 text-center" data-v3-layer="front">
        {{-- PROVA SOCIAL. O número em âmbar é a única cor de destaque da
             página. Quando o .env traz quantos já clonaram (ADR-007), é esse
             o número; enquanto ninguém clonou, a prova que o kit TEM é a
             suíte verde — um fato do projeto, não uma estimativa. Nunca um
             número inventado. --}}
        <p class="v3-pill v3-pill-glass v3-rise mx-auto">
            <span class="flex -space-x-1.5" aria-hidden="true">
                @for ($i = 0; $i < 3; $i++)
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-(--v3-glass-border) bg-(--v3-glass) text-white/80 backdrop-blur">
                        <x-ui-icon name="user-circle" class="h-4 w-4" />
                    </span>
                @endfor
            </span>
            <span class="v3-badge-amber"><span data-v3-count="{{ $proofCount }}">{{ number_format($proofCount, 0, ',', '.') }}</span>+</span>
            {{ $clones > 0 ? __('landing_v3.hero.proof_clones') : __('landing_v3.hero.proof_tests') }}
        </p>

        <h1 class="v3-display mx-auto mt-7 max-w-4xl" data-v3-text>
            {{ __('landing_v3.hero.title_line_1') }}<br>{{ __('landing_v3.hero.title_line_2') }}
        </h1>

        {{-- A DOR, em uma linha: o que custa três meses antes de existir
             produto. A promessa está no título logo acima. --}}
        <p class="v3-lede mx-auto mt-6" data-v3-text>{{ __('landing_v3.hero.subtitle') }}</p>

        <div class="v3-rise relative mx-auto mt-9 flex w-fit flex-wrap items-center justify-center gap-2">
            <a href="{{ $cloneUrl }}" class="v3-btn v3-btn-primary">{{ __('landing_v3.hero.cta_primary') }}</a>
            <a href="{{ route('login') }}" class="v3-btn v3-btn-ghost">{{ __('landing_v3.hero.cta_demo') }}</a>

            {{-- Anotação manuscrita: um aparte, com a seta apontando para o
                 botão que ela comenta. Não é decoração — é a objeção
                 ("quanto custa?") respondida no lugar em que ela nasce. --}}
            <span class="pointer-events-none absolute left-1/2 top-full mt-3 hidden -translate-x-1/2 select-none items-end gap-1 sm:flex lg:left-full lg:top-1/2 lg:ml-6 lg:mt-0 lg:-translate-x-0 lg:-translate-y-1/2" aria-hidden="true">
                <svg viewBox="0 0 60 40" class="h-9 w-14 -scale-x-100 text-(--v3-ink-soft) lg:scale-x-100" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M56 6C40 4 20 10 8 26" />
                    <path d="M4 18c1.6 5.4 2.6 8 4 8.6 1.4.6 4-.6 9-2.6" />
                </svg>
                <span class="v3-hand">{{ __('landing_v3.hero.note') }}</span>
            </span>
        </div>

        {{-- A segunda (e última) anotação manuscrita da página. Sem seta: uma
             seta a mais transformaria o aparte em diagrama. --}}
        <p class="v3-hand v3-rise mx-auto mt-5 w-fit rotate-[3deg]">{{ __('landing_v3.hero.note_secondary') }}</p>
    </div>

    {{-- PLANO DO MEIO (2/2) — o LEQUE: o produto como objeto físico. As telas
         são as capturas reais do kit; os chips flutuam por cima delas com os
         fatos que a imagem sozinha não conta. --}}
    <div class="relative z-10 mx-auto mt-14 w-full max-w-6xl px-4" data-v3-layer="mid">
        <h2 class="sr-only">{{ __('landing_v3.hero.screens_heading') }}</h2>

        <div class="v3-fan v3-rise" data-v3-fan>
            @foreach ($screens as $screen)
                @php $key = $screen['key']; @endphp
                <figure
                    class="v3-fan-card"
                    style="--v3-fan-angle: {{ $screen['angle'] }}deg; --v3-fan-lift: {{ $screen['lift'] }}px; z-index: {{ $loop->index === 2 ? 4 : 3 - abs($loop->index - 2) }}"
                    data-v3-fan-card
                >
                    {{-- Duas capturas por tela (clara e escura): a v3 tem céu
                         de dia e de noite, e um print claro dentro do tema
                         escuro seria uma janela acesa numa sala apagada. --}}
                    <img
                        src="{{ asset("img/landing-v3/{$key}-light-1440.webp") }}"
                        srcset="{{ asset("img/landing-v3/{$key}-light-720.webp") }} 720w, {{ asset("img/landing-v3/{$key}-light-1440.webp") }} 1440w"
                        sizes="(max-width: 640px) 80vw, 21rem"
                        alt="{{ __("landing_v3.hero.screens.{$key}.alt") }}"
                        width="1440"
                        height="900"
                        loading="{{ $loop->index === 2 ? 'eager' : 'lazy' }}"
                        fetchpriority="{{ $loop->index === 2 ? 'high' : 'auto' }}"
                        decoding="async"
                        class="block dark:hidden"
                    >
                    <img
                        src="{{ asset("img/landing-v3/{$key}-dark-1440.webp") }}"
                        srcset="{{ asset("img/landing-v3/{$key}-dark-720.webp") }} 720w, {{ asset("img/landing-v3/{$key}-dark-1440.webp") }} 1440w"
                        sizes="(max-width: 640px) 80vw, 21rem"
                        alt="{{ __("landing_v3.hero.screens.{$key}.alt") }}"
                        width="1440"
                        height="900"
                        loading="lazy"
                        decoding="async"
                        class="hidden dark:block"
                    >
                    {{-- Só a carta CENTRAL mostra o rótulo. As laterais ficam
                         cobertas em quase toda a largura: quatro pílulas ali
                         viravam uma pilha de etiquetas no meio do leque. Para
                         quem lê por leitor de tela, todas continuam nomeadas
                         (o alt de cada captura diz que tela é). --}}
                    <figcaption class="v3-fan-label v3-pill {{ $loop->index === 2 ? '' : 'sr-only' }}">{{ __("landing_v3.hero.screens.{$key}.label") }}</figcaption>
                </figure>
            @endforeach

            {{-- Os chips não repetem o número da pílula de prova social: cada
                 um traz um fato que a captura embaixo não conta. --}}
            <span class="v3-chip v3-pill left-[10%] top-[16%] sm:left-[14%]">{{ __('landing_v3.hero.chip_tenancy') }}</span>
            <span class="v3-chip v3-pill right-[9%] top-[8%] sm:right-[12%]">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-(--v3-amber) text-(--v3-amber-ink)" aria-hidden="true">
                    <x-ui-icon name="check" class="h-3 w-3" />
                </span>
                {{ __('landing_v3.hero.chip_2fa') }}
            </span>
            <span class="v3-chip v3-pill bottom-[16%] left-[22%] hidden sm:inline-flex">{{ __('landing_v3.hero.chip_api_keys') }}</span>
            <span class="v3-chip v3-pill bottom-[10%] right-[20%] hidden sm:inline-flex">{{ __('landing_v3.hero.chip_lgpd') }}</span>
        </div>
    </div>
</section>
