import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(SplitText, ScrollTrigger);

/**
 * REVELAÇÃO DE TEXTO POR LINHA — em TODA seção, não só no herói.
 *
 * O título é quebrado em linhas, cada linha ganha uma máscara
 * (`overflow: hidden`) e sobe de dentro dela. É o gesto que separa um texto
 * que "aparece" de um texto que É REVELADO: a máscara faz a linha existir
 * antes de ser lida, como uma faixa de log sendo impressa.
 *
 * Duas coisas que quase sempre saem erradas neste efeito e aqui não saem:
 *
 * - A quebra em linhas depende da FONTE e da LARGURA. Split feito antes da
 *   fonte carregar quebra nos lugares errados; por isso o módulo espera
 *   `document.fonts.ready`, e refaz o split quando a janela muda de largura.
 * - Um <h2> quebrado em <div>s perde o texto para o leitor de tela. O
 *   SplitText no padrão `aria: "auto"` põe a frase inteira no aria-label do
 *   elemento e esconde os pedaços.
 *
 * Com movimento reduzido: sem máscara e sem deslocamento, só a chegada da
 * opacidade — a informação continua chegando, o percurso não.
 */
export function initTextReveals(reduced) {
    const targets = [...document.querySelectorAll('[data-lv2-lines]')];

    if (targets.length === 0) return;

    const fonts = document.fonts?.ready ?? Promise.resolve();

    Promise.race([fonts, new Promise((resolve) => setTimeout(resolve, 1200))]).then(() => {
        const splits = new Map();

        function build(element) {
            splits.get(element)?.revert();

            if (reduced) {
                gsap.fromTo(
                    element,
                    { opacity: 0 },
                    {
                        opacity: 1,
                        duration: 0.3,
                        ease: 'none',
                        scrollTrigger: { trigger: element, start: 'top 88%', once: true },
                    },
                );

                return;
            }

            const split = new SplitText(element, {
                type: 'lines',
                linesClass: 'lv2-line-reveal',
                tag: 'span',
                mask: 'lines',
            });

            splits.set(element, split);

            gsap.fromTo(
                split.lines,
                { yPercent: 108, opacity: 0 },
                {
                    yPercent: 0,
                    opacity: 1,
                    duration: 0.9,
                    ease: 'expo.out',
                    stagger: 0.08,
                    scrollTrigger: { trigger: element, start: 'top 88%', once: true },
                },
            );
        }

        targets.forEach(build);

        // Rebuild só quando a LARGURA muda: no mobile, rolar a página troca a
        // altura da viewport (barra do navegador) e refazer o split a cada
        // troca dessas faria o título piscar sem motivo.
        let lastWidth = window.innerWidth;
        let timer;

        window.addEventListener('resize', () => {
            if (window.innerWidth === lastWidth) return;

            lastWidth = window.innerWidth;
            clearTimeout(timer);

            timer = setTimeout(() => {
                targets.forEach((element) => {
                    splits.get(element)?.revert();
                    gsap.set(element, { clearProps: 'opacity' });
                    build(element);
                });

                ScrollTrigger.refresh();
            }, 220);
        });
    });
}
