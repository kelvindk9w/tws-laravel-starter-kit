import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

/**
 * CONTADORES — os números da faixa de confiança sobem quando entram na tela.
 *
 * Só conta o que é CONTÁVEL: o `data-lv2-count` só é posto pelo Blade nos
 * valores numéricos. "MIT" e "2026-09-05" ficam parados, porque uma data que
 * "sobe" de zero não é uma animação, é uma mentira animada.
 *
 * O valor final já está no HTML antes de qualquer JS (sem JS o número está
 * correto na tela); o contador começa em 0 só no instante em que assume, e
 * o formato de milhar é o do idioma da página.
 *
 * `tabular-nums` no CSS é o que impede a linha de tremer enquanto o número
 * corre — sem isso, cada dígito muda de largura e o rótulo ao lado dança.
 */
export function initCounters(reduced) {
    const targets = [...document.querySelectorAll('[data-lv2-count]')];

    if (targets.length === 0) return;

    const format = new Intl.NumberFormat(document.documentElement.lang);

    targets.forEach((element) => {
        const target = Number(element.dataset.lv2Count);

        if (!Number.isFinite(target) || target <= 0) return;

        const suffix = element.dataset.lv2CountSuffix ?? '';
        const counter = { value: 0 };

        const write = () => {
            element.textContent = format.format(Math.round(counter.value)) + suffix;
        };

        gsap.to(counter, {
            value: target,
            // Movimento reduzido: a contagem existe, mas curta — quem pediu
            // menos movimento não pediu para esperar mais.
            duration: reduced ? 0.4 : 1.5,
            ease: 'expo.out',
            onUpdate: write,
            scrollTrigger: {
                trigger: element,
                start: 'top 88%',
                once: true,
                onEnter: () => {
                    counter.value = 0;
                    write();
                },
            },
        });
    });

    ScrollTrigger.refresh();
}
