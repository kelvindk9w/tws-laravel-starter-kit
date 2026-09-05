{{-- Marca de tecnologia — SVG inline, 24×24, traço único de 1,6, e o CUBO em
     que ela vive.

     Por que marcas AUTORAIS e não os logotipos oficiais: redesenhar de memória
     o logo de outra empresa erra a marca dela e coloca arte de terceiro dentro
     de um kit MIT. Cada tecnologia ganha um desenho próprio no MESMO sistema de
     traço (monoline, pontas e cantos redondos) sobre a COR DA MARCA dela — que
     é o que faz o objeto ser reconhecido de longe. No arco do rodapé o nome
     ainda está escrito ao lado; no herói os objetos são ambiente, não legenda.

     Este arquivo é a única fonte do traço E da cor: o 3D não redesenha nada,
     ele levanta estes mesmos <svg> do DOM, lê a cor no `data-v3-color` do cubo
     e monta o material (resources/js/landing-v3/marks.js e sky3d.js).

     Uso:
       @include('landing-v3.tech-mark', ['name' => 'laravel'])                    só o traço
       @include('landing-v3.tech-mark', ['name' => 'laravel', 'cube' => true])    o objeto --}}
@php
    $marks = [
        // Pico angular com travessão — a silhueta que o "L" do Laravel faz.
        'laravel' => ['M5 20 12 4l7 16', 'M9 14h6'],

        // Chevrons de código com a barra no meio.
        'php' => ['M8 8l-4 4 4 4', 'M16 8l4 4-4 4', 'M13.5 6.5l-3 11'],

        // Cilindro de banco: tampa, corpo e dois anéis.
        'postgres' => ['M12 3c4.4 0 8 1.1 8 2.5S16.4 8 12 8 4 6.9 4 5.5 7.6 3 12 3z', 'M4 5.5v13C4 19.9 7.6 21 12 21s8-1.1 8-2.5v-13', 'M4 10.5c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5', 'M4 15c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5'],

        // Camadas empilhadas — a memória em blocos.
        'redis' => ['M12 4.5 3.5 8 12 11.5 20.5 8 12 4.5z', 'M3.5 12 12 15.5 20.5 12', 'M3.5 16 12 19.5 20.5 16'],

        // Contêineres sobre o casco, com a linha d'água.
        'docker' => ['M3 12.5h18a7 7 0 0 1-7 7H10a7 7 0 0 1-7-7z', 'M7 8.5h3v4H7z', 'M11.5 8.5h3v4h-3z', 'M11.5 4.5h3v3h-3z', 'M2 20.5c1.6.9 3.2.7 4.5-.5'],

        // Raio no fio: o componente que responde sem recarregar a página.
        'livewire' => ['M13.5 2.5 7 13h4l-1 8.5L17 11h-4l.5-8.5z', 'M4 6.5C2.6 8 2 10 2 12', 'M20 17.5c1.4-1.5 2-3.5 2-5.5'],

        // Lâmpada com o filamento visível.
        'filament' => ['M9 18a6 6 0 1 1 6 0v1.5a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 19.5V18z', 'M9.5 18h5', 'M10 14.5l1-2.5 1 2.5 1-2.5 1 2.5'],

        // Duas ondas sobrepostas.
        'tailwind' => ['M3 10.5c1.2-3.4 3.3-4.3 6.2-2.6 1.7 1 2.6 2.4 4.6 1.9 1.4-.3 2.3-1.3 3.7-3.4', 'M6.5 16.5c1.2-3.4 3.3-4.3 6.2-2.6 1.7 1 2.6 2.4 4.6 1.9 1.4-.3 2.3-1.3 3.7-3.4'],
    ];

    // Cor da MARCA de cada tecnologia. Não entra na paleta do kit: vive só
    // dentro do objeto (cubo em CSS e material 3D), como pintura de um objeto
    // físico. É o que torna oito cubos distinguíveis a 40px de distância.
    $colors = [
        'laravel' => '#f0433a',
        'php' => '#6470b0',
        'postgres' => '#336791',
        'redis' => '#a41e11',
        'docker' => '#1b7ad4',
        'livewire' => '#fb70a9',
        'filament' => '#e08c05',
        'tailwind' => '#0f97c4',
    ];

    $paths = $marks[$name] ?? [];
    $color = $colors[$name] ?? '#6470b0';
    $asCube = $cube ?? false;
@endphp

@if ($asCube)
    <span
        class="v3-tech-cube {{ $cubeClass ?? 'h-16 w-16' }}"
        style="--v3-tech: {{ $color }}; --v3-tech-tilt: {{ $tilt ?? '-8deg' }}"
        data-v3-color="{{ $color }}"
        data-v3-tech="{{ $name }}"
    >
        @include('landing-v3.tech-mark', ['name' => $name, 'class' => $class ?? 'h-7 w-7', 'cube' => false])
    </span>
@else
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" data-v3-mark="{{ $name }}" class="{{ $class ?? 'h-6 w-6' }}">
        @foreach ($paths as $path)
            <path d="{{ $path }}" />
        @endforeach
    </svg>
@endif
