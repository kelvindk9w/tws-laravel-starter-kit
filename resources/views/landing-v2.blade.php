{{--
    LANDING "O RASTRO" (/v2) — versão alternativa da home, para comparação
    lado a lado com a landing atual (/). Rota nomeada: landing.v2.

    A tese: todo sistema seguro é um que se lembra. A página INTEIRA é um log
    de auditoria e o scroll é a linha do tempo — as linhas de log caem no
    herói, se alinham e viram o título; a espinha à esquerda carimba o tempo
    conforme se desce; e o momento-assinatura é a redação LGPD acontecendo na
    frente do visitante, com a saída REAL do Redactor do kit.

    Nada de número inventado: testes, licença, versão e data de build vêm do
    LandingV2Controller (suíte Pest real, composer.json, config/platform.php
    e o manifest do Vite). Nenhuma string solta: tudo em lang/*/landing_v2.php.

    Cabeçalho e rodapé são os MESMOS do site (<x-site-header>/<x-site-footer>,
    via <x-layouts.site>): esta página muda a direção de arte, não o produto.
    A "variante escura" não virou um cabeçalho novo — resources/css/landing-v2.css
    retona os tokens do kit (superfícies, fios e a rampa de cinzas) dentro
    desta rota, e o chrome inteiro adota o mundo sozinho, nos dois temas.
--}}
{{-- `theme-default="dark"`: o mundo padrão desta tela é a TINTA (ver
     resources/js/landing-v2/theme-world.js para a regra completa). Quem
     escolheu "claro" explicitamente continua no papel — a página declara um
     padrão, não sequestra a preferência de ninguém. --}}
<x-layouts.site
    :title="__('landing_v2.meta.title', ['platform' => platform()->name])"
    body-class="lv2"
    theme-default="dark"
>
    <x-slot:head>
        {{-- Bundle EXCLUSIVO da /v2 (GSAP + Lenis + Instrument Serif). A
             landing atual e o painel não carregam nada disto. --}}
        @vite(['resources/css/landing-v2.css', 'resources/js/landing-v2.js'])

        {{-- Anti-flash do mundo da /v2, ANTES do primeiro paint. O script do
             kit (partials/theme-script) já resolve o caso "sem escolha", via
             o data-theme-default="dark" acima; falta o caso em que o valor
             guardado é literalmente 'system' num SO claro — sem esta linha a
             página abriria em papel por um frame antes do guarda corrigir.
             Inline mínimo, coberto pela CSP base ('unsafe-inline'). --}}
        <script>
            (function () {
                var stored = null;
                try { stored = localStorage.getItem('theme'); } catch (e) { /* storage indisponível */ }
                if (stored !== 'light') document.documentElement.classList.add('dark');
            })();
        </script>

        <meta name="description" content="{{ __('landing_v2.meta.description') }}">
    </x-slot:head>

    <x-slot:headerExtra>
        @include('landing-v2.sound-toggle')
    </x-slot:headerExtra>

    @php
        // Dados da página para o JS: nada de fetch na API, nada de string de
        // UI montada dentro do bundle. Um <script type="application/json">
        // não executa — é só transporte, e continua coberto pela CSP estrita.
        $lv2Payload = [
            'trace' => $traceLines,
            'audits' => $auditedPayloads,
            'steps' => collect($steps)
                ->map(fn (array $step, int $i): array => [
                    'command' => $step['command'],
                    'output' => __('landing_v2.mechanism.steps.'.$i.'.output', ['tests' => $stats['tests']]),
                ])
                ->all(),
            'repo' => platform()->repoUrl,
            'i18n' => [
                'redacting' => __('landing_v2.thesis.redacting'),
                'redacted' => __('landing_v2.thesis.redacted'),
                'copied' => __('landing_v2.depth.copied'),
                'copy' => __('landing_v2.depth.copy'),
                'cloneCopied' => __('landing_v2.hero.clone_copied'),
                'cloneCopy' => __('landing_v2.hero.clone_copy'),
                'soundOn' => __('landing_v2.sound.on'),
                'soundOff' => __('landing_v2.sound.off'),
                'stars' => __('landing_v2.community.stars'),
                'liveEmpty' => __('landing_v2.hero.live_empty'),
                'liveRedacted' => __('landing_v2.hero.live_redacted'),
                'liveClean' => __('landing_v2.hero.live_clean'),
            ],
            // As legendas das regras de redação — as MESMAS da seção da tese,
            // porque a regra que age no herói é a mesma que age lá embaixo.
            'rules' => [
                'key' => __('landing_v2.thesis.rules.key'),
                'email' => __('landing_v2.thesis.rules.email'),
                'document' => __('landing_v2.thesis.rules.document'),
                'card' => __('landing_v2.thesis.rules.card'),
            ],
            'proof' => [
                'send' => __('landing_v2.proof.twofa_send'),
                'sending' => __('landing_v2.proof.twofa_sending'),
                'sent' => __('landing_v2.proof.twofa_sent', ['code' => ':code']),
                'ok' => __('landing_v2.proof.twofa_ok'),
                'error' => __('landing_v2.proof.twofa_error', ['attempts' => ':attempts']),
            ],
        ];
    @endphp

    <script type="application/json" id="lv2-data">{!! json_encode($lv2Payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <main class="relative flex-1">
        {{-- A ESPINHA: o rastro literal. O fio do tempo cresce em âmbar com
             o scroll e carrega um marcador por seção, com o estado do ciclo
             de vida ao lado — INICIADA no topo, CONCLUÍDA no fim. Ler a
             página é uma requisição chegando ao fim.

             Decoração estrutural, não conteúdo: fora da árvore de
             acessibilidade (o mesmo estado já é dito por extenso na seção da
             tese) e escondida abaixo de 1280px, onde não há goteira livre. --}}
        @php
            $spineMarks = [
                ['at' => '#mecanismo', 'label' => 'INICIADA'],
                ['at' => '#modulos', 'label' => 'PROCESSANDO'],
                ['at' => '#redacao', 'label' => 'REDIGIDA'],
                ['at' => '#prova', 'label' => 'VERIFICADA'],
                ['at' => '#repositorio', 'label' => 'CONCLUIDA'],
            ];
        @endphp

        <div class="lv2-spine" aria-hidden="true">
            @foreach ($spineMarks as $mark)
                <span class="lv2-mark" data-lv2-mark="{{ $mark['at'] }}">
                    <span class="lv2-mark-label">{{ $mark['label'] }}</span>
                </span>
            @endforeach
        </div>
        <div class="lv2-spine-stamp" aria-hidden="true" data-lv2-stamp>00:00:00</div>

        @include('landing-v2.hero')
        @include('landing-v2.trust')
        @include('landing-v2.mechanism')
        @include('landing-v2.depth')
        @include('landing-v2.thesis')
        @include('landing-v2.proof')
        @include('landing-v2.community')
        @include('landing-v2.cta')
    </main>
</x-layouts.site>
