import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

/**
 * Rolagem suave (Lenis) amarrada ao ticker do GSAP.
 *
 * Por que amarrar em vez de deixar cada um no seu loop: com dois
 * requestAnimationFrame concorrentes, o ScrollTrigger lê uma posição de
 * scroll que o Lenis ainda vai mudar no mesmo frame — o resultado é o
 * tremor de um quadro que todo mundo já viu numa landing com scroll suave.
 *
 * Com `prefers-reduced-motion`, o Lenis NÃO entra: rolagem com inércia é
 * exatamente o tipo de movimento que quem ativou essa preferência pediu para
 * não ter. A página continua inteira, com a rolagem nativa do sistema.
 */
export function initSmoothScroll(reduced) {
    if (reduced) return null;

    const lenis = new Lenis({
        duration: 1.05,
        // Curva quase exponencial: rápido no começo, longa desaceleração.
        easing: (t) => Math.min(1, 1.001 - 2 ** (-10 * t)),
        smoothWheel: true,
        // O toque já tem inércia nativa no sistema operacional; duplicá-la
        // deixa a página escorregadia e imprecisa no polegar.
        syncTouch: false,
    });

    lenis.on('scroll', ScrollTrigger.update);

    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    return lenis;
}
