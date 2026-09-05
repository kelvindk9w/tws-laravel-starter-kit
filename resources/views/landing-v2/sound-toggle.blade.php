{{-- Botão de som da /v2 — ao lado do seletor de idioma, por decisão do dono.

     MUDO POR PADRÃO, sempre: som que começa sozinho é o pior comportamento
     que uma página pode ter. Ligado, o que se ouve é uma textura de
     "terminal/pulso" gerada na hora pela Web Audio API (sem arquivo externo,
     sem request, sem CDN) — um clique curto e grave a cada linha de log que
     nasce, no volume de um teclado mecânico ouvido de outra sala.

     O ícone MOSTRA o estado (as ondas só existem quando o som está ligado):
     um ícone que precisa ser clicado para revelar em que estado está não é
     um controle, é um enigma. Ele aparece duas vezes (barra e gaveta do
     mobile) — por isso o estado é sincronizado por classe, nunca por id. --}}
<button
    type="button"
    class="lv2-sound"
    data-lv2-sound
    aria-pressed="false"
    aria-label="{{ __('landing_v2.sound.label') }}"
    title="{{ __('landing_v2.sound.off') }}"
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
        <path d="M11 5.5 6.75 9H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h2.25L11 18.5a.5.5 0 0 0 .8-.4V5.9a.5.5 0 0 0-.8-.4Z" />
        <g class="lv2-sound-waves">
            <path d="M15.5 9.75a3 3 0 0 1 0 4.5" />
            <path d="M18 7.5a6.5 6.5 0 0 1 0 9" />
        </g>
    </svg>
</button>
