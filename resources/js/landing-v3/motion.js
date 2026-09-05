// Motion da v3 — UMA cena, dirigida pelo scroll.
//
// O momento autoral é o HERÓI PINADO: durante as primeiras ~1,6 tela a página
// não sobe, a CENA se abre. Três planos com paralaxe distinta (fundo: céu e
// nuvens; meio: leque de telas e objetos 3D; frente: tipografia e CTAs) se
// afastam em velocidades diferentes, o leque abre as cartas e os objetos de
// vidro orbitam. Só depois disso a página "solta" e rola normalmente.
//
// Profundidade é o que separa uma página construída de um template: sem
// velocidades diferentes por plano, paralaxe é só um translate.
//
// Fora do herói, cada seção entra com o texto revelado por LINHA (SplitText,
// gratuito desde o GSAP 3.13) e os números contam até o valor. Nada disso
// existe sob `prefers-reduced-motion`: lá o conteúdo nasce inteiro e parado.

const REVEAL_START = 'top 84%';

/** Conta 0 → valor ao entrar em cena. O separador é o do idioma da página. */
function initCounters(gsap, ScrollTrigger, reducedMotion) {
    const nodes = [...document.querySelectorAll('[data-v3-count]')];
    const format = new Intl.NumberFormat(document.documentElement.lang || undefined);

    for (const node of nodes) {
        const target = Number(node.dataset.v3Count);

        if (reducedMotion || Number.isNaN(target)) {
            node.textContent = format.format(target);
            continue;
        }

        const state = { value: 0 };
        node.textContent = format.format(0);

        const run = () =>
            gsap.to(state, {
                value: target,
                duration: 1.4,
                ease: 'power2.out',
                onUpdate: () => {
                    node.textContent = format.format(Math.round(state.value));
                },
            });

        // Se o número JÁ está na tela quando a página abre, ele conta agora.
        // Esperar o scroll deixaria um "0+" parado acima da dobra — que é
        // exatamente o contrário do que um contador de prova social faz.
        if (node.getBoundingClientRect().top < window.innerHeight) {
            run();
            continue;
        }

        ScrollTrigger.create({ trigger: node, start: REVEAL_START, once: true, onEnter: run });
    }
}

/** Revela texto por LINHA, atrás de uma máscara.
 *
 *  Espera as fontes: dividir linhas com a fonte de fallback e reflowar quando
 *  a Space Grotesk chega empurra a página duas vezes (CLS). */
async function initTextReveals(gsap, ScrollTrigger, SplitText) {
    if (document.fonts?.ready) {
        await document.fonts.ready;
    }

    for (const element of document.querySelectorAll('[data-v3-text]')) {
        // `lines` com máscara: a linha sobe de dentro do próprio bloco, em vez
        // de aparecer por opacidade. É o corte que dá peso ao título.
        const split = new SplitText(element, { type: 'lines', mask: 'lines', linesClass: 'v3-line' });

        gsap.from(split.lines, {
            yPercent: 110,
            opacity: 0,
            duration: 0.9,
            ease: 'power4.out',
            stagger: 0.07,
            scrollTrigger: { trigger: element, start: REVEAL_START, once: true },
        });
    }

    ScrollTrigger.refresh();
}

/** Paralaxe pelo ponteiro: escreve `translate`, que compõe com o `transform`
 *  do scroll em vez de brigar com ele. */
function initPointerParallax(hero, reducedMotion) {
    if (reducedMotion || window.matchMedia('(pointer: coarse)').matches) {
        return;
    }

    let raf = 0;
    let targetX = 0;
    let targetY = 0;
    let x = 0;
    let y = 0;

    const frame = () => {
        x += (targetX - x) * 0.06;
        y += (targetY - y) * 0.06;
        hero.style.setProperty('--v3-px', x.toFixed(4));
        hero.style.setProperty('--v3-py', y.toFixed(4));
        raf = Math.abs(targetX - x) > 0.001 || Math.abs(targetY - y) > 0.001 ? requestAnimationFrame(frame) : 0;
    };

    hero.addEventListener(
        'pointermove',
        (event) => {
            const rect = hero.getBoundingClientRect();
            targetX = (event.clientX - rect.left) / rect.width - 0.5;
            targetY = (event.clientY - rect.top) / rect.height - 0.5;
            if (raf === 0) {
                raf = requestAnimationFrame(frame);
            }
        },
        { passive: true },
    );

    hero.addEventListener('pointerleave', () => {
        targetX = 0;
        targetY = 0;
        if (raf === 0) {
            raf = requestAnimationFrame(frame);
        }
    });
}

export function initMotion({ gsap, ScrollTrigger, SplitText, Lenis, reducedMotion }) {
    const revealed = [...document.querySelectorAll('.v3-rise')];

    if (reducedMotion || gsap === null) {
        revealed.forEach((element) => element.classList.add('is-in'));
        initCounters(gsap, ScrollTrigger, true);
        return null;
    }

    const hero = document.querySelector('[data-v3-hero]');

    // Herói: entra imediatamente (é o que já está na tela), escalonado.
    if (hero !== null) {
        [...hero.querySelectorAll('.v3-rise')].forEach((element, index) => {
            element.style.setProperty('--v3-delay', `${index * 90}ms`);
            requestAnimationFrame(() => element.classList.add('is-in'));
        });
    }

    // Resto da página: aparece ao entrar em cena, uma vez.
    for (const element of revealed) {
        if (hero !== null && hero.contains(element)) {
            continue;
        }
        ScrollTrigger.create({
            trigger: element,
            start: REVEAL_START,
            once: true,
            onEnter: () => element.classList.add('is-in'),
        });
    }

    initTextReveals(gsap, ScrollTrigger, SplitText);
    initCounters(gsap, ScrollTrigger, false);

    if (hero !== null) {
        initPointerParallax(hero, reducedMotion);

        const fan = hero.querySelector('[data-v3-fan]');
        const cards = fan === null ? [] : [...fan.querySelectorAll('[data-v3-fan-card]')];
        const middle = (cards.length - 1) / 2;
        const progress = { value: 0 };

        // O PIN só existe onde ele cabe: abaixo de 768px a tela tem 844px de
        // altura e um herói travado por 1,6 tela vira uma parede. Lá a cena
        // acontece na rolagem normal.
        const canPin = window.matchMedia('(min-width: 768px)').matches;

        const timeline = gsap.timeline({
            scrollTrigger: {
                trigger: hero,
                start: 'top top',
                end: '+=160%',
                scrub: 0.8,
                pin: canPin,
                pinSpacing: canPin,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });

        // TRÊS PLANOS, TRÊS VELOCIDADES. O fundo quase não anda (é o céu, está
        // longe); o meio acompanha; a frente sai de cena primeiro, que é o que
        // faz o leque "subir" enquanto o título se despede.
        timeline
            .to('[data-v3-layer="back"]', { yPercent: 10, scale: 1.1, ease: 'none' }, 0)
            .to('[data-v3-layer="mid"]', { yPercent: -7, ease: 'none' }, 0)
            .to('[data-v3-layer="front"]', { yPercent: -16, opacity: 0.12, ease: 'none' }, 0)
            .to(progress, {
                value: 1,
                ease: 'none',
                onUpdate: () => {
                    // Os objetos 3D orbitam pelo MESMO progresso. Evento em vez
                    // de referência direta: a cena 3D pode nem existir (WebGL
                    // ausente, aparelho fraco) e o herói continua inteiro.
                    document.dispatchEvent(new CustomEvent('v3:scene', { detail: { progress: progress.value } }));
                },
            }, 0);

        if (cards.length > 0) {
            // O leque ABRE conforme a cena avança: no repouso as cartas estão
            // quase fechadas, no fim do pin elas se espalham e sobem.
            timeline.fromTo(
                cards,
                { rotate: (index) => (index - middle) * 3.5, yPercent: 6 },
                {
                    rotate: (index) => (index - middle) * 9,
                    y: (index) => Math.abs(index - middle) * 30,
                    yPercent: -4,
                    ease: 'none',
                    stagger: 0.02,
                },
                0,
            );
        }
    }

    const lenis = new Lenis({ duration: 1.05, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    return lenis;
}
