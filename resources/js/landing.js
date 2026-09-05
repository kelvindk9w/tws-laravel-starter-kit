// Entrada Vite da landing oficial "Céu" — carregada SÓ na rota /.
//
// Orçamento: o que chega no primeiro paint é pequeno de propósito. GSAP,
// ScrollTrigger e Lenis entram por import() dinâmico e só quando há motion a
// fazer; o Three.js entra num terceiro pedaço e só quando o aparelho pode
// com ele. Quem pede `prefers-reduced-motion` baixa APENAS este arquivo.
//
// Sem CDN: tudo é empacotado (a CSP do kit é `script-src 'self'`).

import { initSplit } from './landing/split.js';
import { initBento } from './landing/bento.js';

/**
 * A cápsula do cabeçalho POUSA quando o céu acaba — e volta a flutuar quando
 * o céu do rodapé chega.
 *
 * Ela é translúcida porque está SOBRE O CÉU; sobre a superfície branca do
 * miolo, vidro branco com links brancos é uma barra vazia. Então a regra não é
 * "passou do herói", é "tem céu embaixo de mim": os dois ambientes da página
 * (`.sky-env`) são observados, e a barra pousa quando nenhum deles está na
 * faixa dela. Sem scroll listener, sem medir nada a cada quadro — e roda
 * também para quem pediu `prefers-reduced-motion`, porque isto não é
 * animação, é legibilidade.
 */
function initNavLanding() {
    const header = document.querySelector('.sky-nav');
    const skies = [...document.querySelectorAll('.sky-env')];

    if (header === null || skies.length === 0 || typeof IntersectionObserver !== 'function') {
        return;
    }

    const under = new Set();

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    under.add(entry.target);
                } else {
                    under.delete(entry.target);
                }
            }

            header.toggleAttribute('data-sky-landed', under.size === 0);
        },
        // A faixa observada é a da própria barra (topo + 4,25rem de altura):
        // o que importa é o que passa POR BAIXO dela, não o que está no meio
        // da tela.
        { rootMargin: '0px 0px -100% 0px', threshold: 0 },
    );

    for (const sky of skies) {
        observer.observe(sky);
    }
}

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * O aparelho aguenta vidro em tempo real?
 *
 * Não é só WebGL disponível: um notebook de dois núcleos renderiza
 * transmissão a 12 fps, e 12 fps é pior do que o fallback em CSS parado.
 */
function canRunWebgl() {
    if (reducedMotion || document.documentElement.dataset.skyWebgl !== 'on') {
        return false;
    }

    if (window.matchMedia('(max-width: 767px)').matches) {
        return false;
    }

    if ((navigator.hardwareConcurrency ?? 8) < 4 || (navigator.deviceMemory ?? 8) < 4) {
        return false;
    }

    try {
        const probe = document.createElement('canvas');
        return probe.getContext('webgl2') !== null;
    } catch {
        return false;
    }
}

async function boot() {
    initNavLanding();

    // A página nasce inteira: o motion só é ligado depois que o JS confirma
    // que vai mesmo animar (a classe some o estado inicial escondido).
    document.documentElement.dataset.skyMotion = reducedMotion ? '' : 'on';

    let gsap = null;
    let ScrollTrigger = null;

    if (!reducedMotion) {
        const [core, scroll, split, lenisModule, motion] = await Promise.all([
            import('gsap'),
            import('gsap/ScrollTrigger'),
            import('gsap/SplitText'),
            import('lenis'),
            import('./landing/motion.js'),
        ]);

        gsap = core.gsap;
        ScrollTrigger = scroll.ScrollTrigger;
        gsap.registerPlugin(ScrollTrigger, split.SplitText);

        motion.initMotion({
            gsap,
            ScrollTrigger,
            SplitText: split.SplitText,
            Lenis: lenisModule.default,
            reducedMotion,
        });
    } else {
        for (const element of document.querySelectorAll('.sky-rise')) {
            element.classList.add('is-in');
        }
    }

    const split = document.querySelector('[data-sky-split]');
    if (split !== null) {
        initSplit(split, { gsap, ScrollTrigger, reducedMotion });
    }

    const bento = document.querySelector('[data-sky-bento]');
    if (bento !== null) {
        initBento(bento, { reducedMotion });
    }

    if (!canRunWebgl()) {
        return;
    }

    const mounts = [...document.querySelectorAll('[data-sky-3d]')];
    if (mounts.length === 0) {
        return;
    }

    const { createSky3d } = await import('./landing/sky3d.js');

    for (const mount of mounts) {
        // As marcas vêm do fallback que já está na página: uma fonte de
        // verdade para o traço (ver landing/marks.js).
        const source = document.querySelector(mount.dataset.skyMarks);
        const marks = source === null ? [] : [...source.querySelectorAll('svg')];

        if (marks.length === 0) {
            continue;
        }

        try {
            await createSky3d(mount, { layout: mount.dataset.skyLayout ?? 'float', marks });
            // Só agora o fallback sai de cena: se o 3D falhar, ele fica.
            mount.closest('[data-sky-3d-scope]')?.setAttribute('data-sky-3d-active', 'true');
        } catch (error) {
            // Falhar em 3D não pode derrubar a página: o CSS já desenha tudo.
            console.warn('landing: 3D indisponível', error);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
