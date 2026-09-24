import { gsap } from 'gsap';

/**
 * A CADEIA — INICIADA → CONCLUÍDA / ERRO / BLOQUEADA.
 *
 * Os conectores são fios de 1px do próprio HTML (nada de SVG com viewBox):
 * um traço desenhado em SVG precisa de coordenadas fixas, e coordenadas
 * fixas são a razão pela qual diagramas de landing quebram no celular. Aqui
 * o fio é um elemento de layout — ele já sabe onde começa e onde termina em
 * qualquer largura — e o GSAP só anima a escala vertical dele a partir de
 * zero, que é a leitura de "sendo desenhado".
 */
export function initChain(reduced) {
    const chain = document.querySelector('[data-lv2-chain]');

    if (!chain) return;

    const rails = chain.querySelectorAll('[data-lv2-chain-rail]');

    if (rails.length === 0) return;

    gsap.fromTo(
        rails,
        { scaleY: 0 },
        {
            scaleY: 1,
            duration: reduced ? 0.3 : 0.75,
            ease: 'expo.out',
            stagger: reduced ? 0.04 : 0.16,
            scrollTrigger: {
                trigger: chain,
                start: 'top 75%',
                once: true,
            },
        },
    );
}
