// Split-screen: o CÓDIGO de um componente do kit à esquerda, a TELA
// renderizada dele à direita, e uma linha luminosa entre os dois.
//
// O controle é um <input type="range"> de verdade, esticado sobre a área e
// com rótulo. Isso não é purismo: um <div> com mousedown não tem seta do
// teclado, não tem foco e não tem valor lido em voz alta. Aqui arrastar com
// o mouse, com o dedo e com ← → é o MESMO controle.
//
// No scroll (sem preferência por menos movimento), a linha se anima uma vez
// ao entrar em cena — a página demonstra o gesto antes de pedi-lo.

export function initSplit(root, { gsap, ScrollTrigger, reducedMotion }) {
    const range = root.querySelector('[data-sky-split-range]');
    if (range === null) {
        return;
    }

    const apply = (value) => root.style.setProperty('--sky-split', `${value}%`);

    apply(Number(range.value));
    range.addEventListener('input', () => apply(Number(range.value)));

    if (reducedMotion || gsap === null) {
        return;
    }

    // Demonstração de uma vez só: 50% → 78% → 34% → 52%, no ritmo do scroll.
    const state = { value: Number(range.value) };
    const timeline = gsap.timeline({
        paused: true,
        onUpdate: () => {
            range.value = String(Math.round(state.value));
            apply(state.value);
        },
    });

    timeline
        .to(state, { value: 78, duration: 0.9, ease: 'power3.inOut' })
        .to(state, { value: 34, duration: 1.1, ease: 'power3.inOut' })
        .to(state, { value: 52, duration: 0.8, ease: 'power3.out' });

    ScrollTrigger.create({
        trigger: root,
        start: 'top 72%',
        once: true,
        onEnter: () => timeline.play(),
    });

    // Assim que a pessoa toca no controle, a demonstração sai do caminho.
    range.addEventListener('pointerdown', () => timeline.kill(), { once: true });
    range.addEventListener('keydown', () => timeline.kill(), { once: true });
}
