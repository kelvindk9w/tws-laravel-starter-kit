import { gsap } from 'gsap';

/**
 * BENTO — uma célula viva por vez.
 *
 * Padrão tablist do WAI-ARIA, e por um motivo prático: uma grade de seis
 * botões em que só um "está aceso" É um conjunto de abas, e reimplementar o
 * comportamento com div e clique deixaria de fora tudo o que o teclado
 * espera. Setas percorrem (em qualquer direção — a grade é bidimensional e
 * ninguém lembra qual eixo o autor escolheu), Home/End vão às pontas, e só a
 * célula ativa fica na ordem de tabulação.
 *
 * O painel de código troca com um cross-fade curto e sem deslocamento: o
 * conteúdo é código, e código que desliza é código que não dá para ler.
 */
export function initBento() {
    const list = document.querySelector('[data-lv2-bento]');

    if (!list) return;

    const tabs = [...list.querySelectorAll('[role="tab"]')];
    const panels = [...document.querySelectorAll('[data-lv2-panel]')];

    if (tabs.length === 0) return;

    function select(index, { focus = false } = {}) {
        tabs.forEach((tab, i) => {
            const active = i === index;

            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel, i) => {
            if (i === index) {
                panel.hidden = false;
                gsap.fromTo(panel, { opacity: 0 }, { opacity: 1, duration: 0.28, ease: 'power2.out' });
            } else {
                panel.hidden = true;
            }
        });

        if (focus) tabs[index].focus();
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(index));

        // Passar o cursor acende a célula: a grade é feita para ser
        // percorrida, e exigir um clique para cada leitura transformaria
        // seis módulos em seis cliques.
        tab.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') select(index);
        });

        tab.addEventListener('keydown', (event) => {
            const moves = {
                ArrowRight: index + 1,
                ArrowDown: index + 1,
                ArrowLeft: index - 1,
                ArrowUp: index - 1,
                Home: 0,
                End: tabs.length - 1,
            };

            if (!(event.key in moves)) return;

            event.preventDefault();
            select((moves[event.key] + tabs.length) % tabs.length, { focus: true });
        });
    });
}
