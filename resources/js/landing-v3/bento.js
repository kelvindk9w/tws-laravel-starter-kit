// Bento de segurança: UMA célula viva por vez.
//
// Por que uma só: seis cartões acendendo juntos é ruído; um de cada vez conta
// que são seis peças de um mesmo sistema, e o olho segue o ciclo sem esforço.
// O rodízio para quando o ponteiro ou o teclado entram no bento — o realce
// automático não pode disputar com a leitura de quem escolheu uma célula.

export function initBento(root, { reducedMotion }) {
    const cells = [...root.querySelectorAll('[data-v3-cell]')];
    if (cells.length === 0) {
        return;
    }

    // Sem movimento pedido: nada pisca. As bordas do bento já separam as
    // peças; a vida era enfeite, não informação.
    if (reducedMotion) {
        return;
    }

    let index = 0;
    let timer = null;

    const light = (next) => {
        cells.forEach((cell, position) => cell.classList.toggle('is-live', position === next));
    };

    const advance = () => {
        index = (index + 1) % cells.length;
        light(index);
    };

    const play = () => {
        if (timer === null) {
            timer = window.setInterval(advance, 3200);
        }
    };

    const pause = () => {
        window.clearInterval(timer);
        timer = null;
    };

    light(0);
    play();

    root.addEventListener('pointerenter', pause);
    root.addEventListener('pointerleave', play);
    root.addEventListener('focusin', pause);
    root.addEventListener('focusout', play);

    const observer = new IntersectionObserver(([entry]) => (entry.isIntersecting ? play() : pause()), {
        rootMargin: '80px',
    });
    observer.observe(root);

    document.addEventListener('visibilitychange', () => (document.hidden ? pause() : play()));
}
