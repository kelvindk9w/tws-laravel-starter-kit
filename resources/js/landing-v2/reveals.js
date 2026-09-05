import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

/**
 * Chegada das seções.
 *
 * UM movimento autoral, não uma entrada idêntica em cada bloco: os elementos
 * sobem 14px com um ease exponencial e um escalonamento curto dentro do
 * MESMO grupo — o que se lê é uma linha de log sendo escrita, não seis
 * cartões pipocando.
 *
 * Com movimento reduzido, some a distância (só a opacidade chega) e o
 * escalonamento encolhe: continua havendo resposta ao scroll, sem deslocamento.
 */
export function initReveals(reduced) {
    const groups = new Map();

    document.querySelectorAll('.lv2-reveal').forEach((element) => {
        // Agrupa por seção: o escalonamento é entre irmãos da mesma seção,
        // não entre a página inteira.
        const section = element.closest('section') ?? document.body;

        if (!groups.has(section)) groups.set(section, []);
        groups.get(section).push(element);
    });

    groups.forEach((elements, section) => {
        gsap.fromTo(
            elements,
            { opacity: 0, y: reduced ? 0 : 14 },
            {
                opacity: 1,
                y: 0,
                duration: reduced ? 0.25 : 0.75,
                ease: 'expo.out',
                stagger: reduced ? 0.02 : 0.07,
                scrollTrigger: {
                    trigger: section,
                    start: 'top 82%',
                    once: true,
                },
            },
        );
    });

    ScrollTrigger.refresh();
}
