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
    $tech = __('landing_v3.footer.tech');
    $lifts = ['-28px', '-14px', '-4px', '0px', '0px', '-4px', '-14px', '-28px'];
@endphp

<section class="v3-sky v3-sky-inverted relative overflow-hidden pt-24 sm:pt-32">
    <div class="v3-clouds" aria-hidden="true">
        <span class="v3-cloud v3-cloud-2"></span>
        <span class="v3-cloud v3-cloud-3"></span>
    </div>

    <div class="relative z-10 mx-auto max-w-6xl px-4 text-center">
        <h2 class="v3-display mx-auto max-w-3xl" data-v3-text>{{ __('landing_v3.footer.title') }}</h2>
        <p class="v3-lede mx-auto mt-5" data-v3-text>{{ __('landing_v3.footer.subtitle') }}</p>

        <div class="v3-rise mt-9">
            <a href="{{ $cloneUrl }}" class="v3-btn v3-btn-primary">{{ __('landing_v3.footer.cta') }}</a>
        </div>
    </div>

    {{-- Arco de tecnologias. O contêiner do 3D cobre exatamente a faixa do
         arco em CSS: quando o WebGL entra, os objetos aparecem no MESMO
         lugar em que os ladrilhos estavam. --}}
    <div class="relative z-10 mx-auto mt-14 max-w-5xl px-4" data-v3-3d-scope>
        <h3 class="v3-arc-heading v3-rise text-center text-sm font-medium tracking-[-0.005em]">{{ __('landing_v3.footer.tech_heading') }}</h3>

        <div class="relative mt-6 min-h-[12rem]">
            <div class="v3-canvas" data-v3-3d data-v3-layout="arc" data-v3-marks="#v3-arc-marks" aria-hidden="true"></div>

            <ul id="v3-arc-marks" class="v3-arc absolute inset-x-0 bottom-0" data-v3-3d-fallback>
                @foreach ($tech as $key => $label)
                    <li class="v3-arc-item" style="--v3-arc-lift: {{ $lifts[$loop->index] ?? '0px' }}">
                        @include('landing-v3.tech-mark', [
                            'name' => $key,
                            'cube' => true,
                            'cubeClass' => 'h-[clamp(3.25rem,6vw,4.5rem)] w-[clamp(3.25rem,6vw,4.5rem)]',
                            'class' => 'h-7 w-7',
                            'tilt' => ($loop->index % 2 === 0 ? '-' : '').(8 + ($loop->index % 3) * 4).'deg',
                        ])
                        <span class="v3-arc-label">{{ $label }}</span>
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
