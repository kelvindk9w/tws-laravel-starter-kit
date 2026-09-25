// =============================================================================
// LANDING "O RASTRO" (/v2) — ponto de entrada do bundle EXCLUSIVO desta rota.
//
// Nada aqui é carregado pela landing atual, pelo painel ou pelo /admin: é uma
// entrada própria do Vite (ver vite.config.js). GSAP e Lenis existem só nesta
// página, e por isso o custo deles não é pago por ninguém mais.
//
// Regras que valem para todos os módulos:
//
// - CONTEÚDO NÃO É OPCIONAL. Toda animação parte de um estado em que a
//   informação já está legível. Se o JS falhar, quebrar ou nunca rodar, a
//   página continua completa — o `.lv2-js` só é adicionado ao <html> depois
//   que o módulo assume, e uma rede de segurança de 2s revela tudo de
//   qualquer jeito.
// - `prefers-reduced-motion` = MENOS movimento, não zero. Quem pediu menos
//   movimento continua vendo a página responder; some a distância percorrida
//   e some o PIN do herói (rolagem sequestrada é exatamente o que essa
//   preferência pede para não existir), não a resposta.
// - Nenhuma string de UI mora aqui: as que o JS precisa vêm do
//   <script type="application/json" id="lv2-data"> montado no Blade.
// =============================================================================

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

import { readPageData, prefersReducedMotion } from './landing-v2/context.js';
import { initThemeWorld } from './landing-v2/theme-world.js';
import { initSmoothScroll } from './landing-v2/smooth-scroll.js';
import { initReveals } from './landing-v2/reveals.js';
import { initTextReveals } from './landing-v2/text-reveal.js';
import { initAmbient } from './landing-v2/ambient.js';
import { initTraceCanvas } from './landing-v2/trace-canvas.js';
import { initHeroScene } from './landing-v2/hero-scene.js';
import { initLiveRedact } from './landing-v2/live-redact.js';
import { initSpine } from './landing-v2/spine.js';
import { initCounters } from './landing-v2/counters.js';
import { initTerminal } from './landing-v2/terminal.js';
import { initBento } from './landing-v2/bento.js';
import { initRedaction } from './landing-v2/redaction.js';
import { initChain } from './landing-v2/chain.js';
import { initTwoFactorDemo } from './landing-v2/two-factor-demo.js';
import { initStars } from './landing-v2/stars.js';
import { initSound } from './landing-v2/sound.js';
import { initCopyFeedback } from './landing-v2/copy-feedback.js';

gsap.registerPlugin(ScrollTrigger);

const root = document.documentElement;
const page = document.querySelector('.lv2');

if (page) {
    const data = readPageData();
    const reduced = prefersReducedMotion();

    // O mundo desta tela é a TINTA quando não há escolha explícita. Primeiro
    // de todos: qualquer coisa medida depois disso já vê a paleta certa.
    initThemeWorld();

    // A partir daqui o JS é responsável por revelar o conteúdo.
    root.classList.add('lv2-js');

    // Rede de segurança: se qualquer coisa acima falhar (aba em segundo
    // plano, erro de um módulo, observer que nunca dispara), o conteúdo
    // aparece assim mesmo. Animação é enfeite; conteúdo não é opcional.
    const safety = setTimeout(() => root.classList.add('lv2-safety'), 2000);

    // O som é o ÚNICO módulo que fica fora do try: ele é a promessa feita
    // no cabeçalho, e um erro em qualquer efeito visual não pode levar o
    // botão de som junto.
    const sound = initSound(data);

    // O campo interativo também fica fora: é conteúdo funcional do herói,
    // não coreografia.
    initLiveRedact(data);

    try {
        const lenis = initSmoothScroll(reduced);

        // Os planos de fundo nascem antes da cena: é a cena pinada que
        // dirige a velocidade dos dois.
        const ambient = initAmbient(reduced);

        // O MESMO campo, atrás do CTA final, a 40% do ritmo: a página abre e
        // encerra no mesmo material, mas ali a trilha está terminando.
        const closing = initAmbient(reduced, '[data-lv2-ambient-final]', 0.4);

        if (closing) {
            closing.setIntensity(0.75);

            ScrollTrigger.create({
                trigger: '[data-lv2-final]',
                start: 'top bottom',
                end: 'bottom top',
                onToggle: (self) => closing.setRunning(self.isActive),
            });
        }
        const trace = initTraceCanvas(data, reduced, sound);

        initHeroScene({ reduced, ambient, trace, sound });
        initTextReveals(reduced);
        initReveals(reduced);
        initSpine(reduced);
        initCounters(reduced);
        initTerminal(data, reduced);
        initBento();
        initRedaction(data, reduced, sound);
        initChain(reduced);
        initTwoFactorDemo(data);
        initStars(data);
        initCopyFeedback(data);

        // Âncoras internas passam pelo Lenis para não brigarem com ele.
        if (lenis) {
            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href^="#"]:not([href="#"])');
                if (!link) return;

                const target = document.querySelector(link.getAttribute('href'));
                if (!target) return;

                event.preventDefault();
                lenis.scrollTo(target, { offset: -80 });
            });
        }

        ScrollTrigger.refresh();
    } catch (error) {
        // Um efeito quebrado não pode derrubar a página inteira.
        clearTimeout(safety);
        root.classList.add('lv2-safety');
        console.error('[landing-v2]', error);
    }

    // Fontes chegam depois do primeiro paint e mudam a altura das seções:
    // sem este refresh, os gatilhos de scroll (e o pin do herói) ficam
    // calculados sobre a medida da fonte de fallback.
    if (document.fonts?.ready) {
        document.fonts.ready.then(() => ScrollTrigger.refresh());
    }
}
