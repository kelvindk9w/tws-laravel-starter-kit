{{-- RODAPÉ CÉU — o ambiente volta, e a página fecha com UMA ação.

     Um CTA e só: a hierarquia do kit ("um primário, um secundário, o resto é
     link") vira, no fim da página, um botão sozinho. Quem chegou até aqui já
     escolheu; oferecer três caminhos agora é pedir para reconsiderar.

     O arco de tecnologias é a segunda cena 3D da página (a única outra). O
     fallback em CSS é o MESMO arco — mesmos ícones, mesmos rótulos, parado —
     e é dele que o 3D levanta as marcas. --}}
@php
    $cloneUrl = platform()->repoUrl ?? route('register');

    // Arco: as pontas sobem, o centro desce. Os ângulos do CSS e os do
    // Three.js descrevem a MESMA curva (sky3d.js, layout 'arc').
    $tech = __('landing.footer.tech');
    $lifts = ['-28px', '-14px', '-4px', '0px', '0px', '-4px', '-14px', '-28px'];
@endphp

<section class="sky-env sky-env-inverted relative overflow-hidden pt-24 sm:pt-32">
    <div class="sky-clouds" aria-hidden="true">
        <span class="sky-cloud sky-cloud-2"></span>
        <span class="sky-cloud sky-cloud-3"></span>
    </div>

    <div class="relative z-10 mx-auto max-w-6xl px-4 text-center">
        <h2 class="sky-display mx-auto max-w-3xl" data-sky-text>{{ __('landing.footer.title') }}</h2>
        <p class="sky-lede mx-auto mt-5" data-sky-text>{{ __('landing.footer.subtitle') }}</p>

        <div class="sky-rise mt-9">
            <a href="{{ $cloneUrl }}" class="sky-btn sky-btn-primary">{{ __('landing.footer.cta') }}</a>
        </div>

        {{-- O super admin em demonstração é uma PORTA DE DESENVOLVIMENTO: só
             existe onde o login demo está ligado (config/ui.php ←
             DEMO_LOGIN_ENABLED). Em produção ela não aparece — e é por isso
             que o link mora aqui embaixo, como aparte, e não ao lado do CTA:
             um botão que some conforme o ambiente não pode ser um dos dois
             caminhos principais da página. --}}
        @if (config('ui.demo_login.enabled'))
            <p class="sky-rise mt-5">
                <a href="{{ url('/admin') }}" class="sky-quiet-link">{{ __('landing.hero.cta_admin_demo') }}</a>
            </p>
        @endif
    </div>

    {{-- Arco de tecnologias. O contêiner do 3D cobre exatamente a faixa do
         arco em CSS: quando o WebGL entra, os objetos aparecem no MESMO
         lugar em que os ladrilhos estavam. --}}
    <div id="stack" class="relative z-10 mx-auto mt-14 max-w-5xl scroll-mt-24 px-4" data-sky-3d-scope>
        <h3 class="sky-arc-heading sky-rise text-center text-sm font-medium tracking-[-0.005em]">{{ __('landing.footer.tech_heading') }}</h3>

        <div class="relative mt-6 min-h-[12rem]">
            <div class="sky-canvas" data-sky-3d data-sky-layout="arc" data-sky-marks="#sky-arc-marks" aria-hidden="true"></div>

            <ul id="sky-arc-marks" class="sky-arc absolute inset-x-0 bottom-0" data-sky-3d-fallback>
                @foreach ($tech as $key => $label)
                    <li class="sky-arc-item" style="--sky-arc-lift: {{ $lifts[$loop->index] ?? '0px' }}">
                        @include('landing.tech-mark', [
                            'name' => $key,
                            'cube' => true,
                            'cubeClass' => 'h-[clamp(3.25rem,6vw,4.5rem)] w-[clamp(3.25rem,6vw,4.5rem)]',
                            'class' => 'h-7 w-7',
                            'tilt' => ($loop->index % 2 === 0 ? '-' : '').(8 + ($loop->index % 3) * 4).'deg',
                        ])
                        <span class="sky-arc-label">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Rodapé institucional do kit, aqui dentro do céu: variante 'plain'
         (sem a linha superior — o horizonte já separa). Mesmo conteúdo,
         mesmos links, mesma razão social de todas as outras telas. --}}
    <div class="relative z-10 mt-10">
        <x-site-footer variant="plain" />
    </div>
</section>
