// Entrada Vite da landing "Céu" (v3) — carregada SÓ na rota /v3.
//
// Orçamento: o que chega no primeiro paint é pequeno de propósito. GSAP,
// ScrollTrigger e Lenis entram por import() dinâmico e só quando há motion a
// fazer; o Three.js entra num terceiro pedaço e só quando o aparelho pode
// com ele. Quem pede `prefers-reduced-motion` baixa APENAS este arquivo.
//
// Sem CDN: tudo é empacotado (a CSP do kit é `script-src 'self'`).

import { initSplit } from './landing-v3/split.js';
import { initBento } from './landing-v3/bento.js';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * O aparelho aguenta vidro em tempo real?
 *
 * Não é só WebGL disponível: um notebook de dois núcleos renderiza
 * transmissão a 12 fps, e 12 fps é pior do que o fallback em CSS parado.
 */
function canRunWebgl() {
    if (reducedMotion || document.documentElement.dataset.v3Webgl !== 'on') {
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
    // A página nasce inteira: o motion só é ligado depois que o JS confirma
    // que vai mesmo animar (a classe some o estado inicial escondido).
    document.documentElement.dataset.v3Motion = reducedMotion ? '' : 'on';

    let gsap = null;
    let ScrollTrigger = null;

    if (!reducedMotion) {
        const [core, scroll, split, lenisModule, motion] = await Promise.all([
            import('gsap'),
            import('gsap/ScrollTrigger'),
            import('gsap/SplitText'),
            import('lenis'),
            import('./landing-v3/motion.js'),
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
        for (const element of document.querySelectorAll('.v3-rise')) {
            element.classList.add('is-in');
        }
    }

    const split = document.querySelector('[data-v3-split]');
    if (split !== null) {
        initSplit(split, { gsap, ScrollTrigger, reducedMotion });
    }

    const bento = document.querySelector('[data-v3-bento]');
    if (bento !== null) {
        initBento(bento, { reducedMotion });
    }

    if (!canRunWebgl()) {
        return;
    }

    const mounts = [...document.querySelectorAll('[data-v3-3d]')];
    if (mounts.length === 0) {
        return;
    }

    const { createSky3d } = await import('./landing-v3/sky3d.js');

    for (const mount of mounts) {
        // As marcas vêm do fallback que já está na página: uma fonte de
        // verdade para o traço (ver landing-v3/marks.js).
        const source = document.querySelector(mount.dataset.v3Marks);
        const marks = source === null ? [] : [...source.querySelectorAll('svg')];

        if (marks.length === 0) {
            continue;
        }

        try {
            await createSky3d(mount, { layout: mount.dataset.v3Layout ?? 'float', marks });
            // Só agora o fallback sai de cena: se o 3D falhar, ele fica.
            mount.closest('[data-v3-3d-scope]')?.setAttribute('data-v3-3d-active', 'true');
        } catch (error) {
            // Falhar em 3D não pode derrubar a página: o CSS já desenha tudo.
            console.warn('landing-v3: 3D indisponível', error);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
