import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(ScrollTrigger, SplitText);

// =============================================================================
// A CENA DO HERÓI — PINADA, dirigida pelo scroll.
//
// O herói não é uma tela estática com um fade: ele fica PRESO na viewport
// por cerca de duas telas de rolagem, e é a rolagem que dirige a cena. O
// dedo do visitante é o cursor da linha do tempo — que é exatamente a tese
// da página: o scroll É o tempo.
//
// A cena tem QUATRO PLANOS, cada um com paralaxe própria. A profundidade não
// vem de sombra: vem de planos que se movem em velocidades diferentes.
//
//   0. ambiente  — campo procedural (canvas)      · paralaxe 0,06 · o mais lento
//   1. rastro    — as linhas de log caindo        · paralaxe 0,18
//   2. objeto    — o bloco de log flutuante       · paralaxe 0,42 · com tilt
//   3. frente    — tipografia e CTAs              · paralaxe 0,85 · o mais rápido
//
// DUAS linhas do tempo, e a divisão entre elas é uma decisão de conteúdo:
//
//   INTRO (toca sozinha, ~1,1s) — o título se forma a partir dos glifos de
//   log, o subtítulo e os CTAs sobem e o objeto entra. Isto NÃO pode depender
//   de rolagem: um herói que só existe depois que a pessoa rola é um herói em
//   branco no primeiro paint — mata o LCP e a primeira impressão de uma vez.
//
//   CENA (pinada, presa ao scroll) — é a rolagem que dirige a profundidade:
//   as linhas de log FREIAM até quase parar, o campo ambiente acende, os
//   quatro planos se separam em velocidades diferentes (paralaxe) e o herói
//   entrega a página. Aqui o dedo do visitante é o cursor da linha do tempo,
//   que é a tese da página.
//
// GLIFOS DE LARGURA TRAVADA: a display é uma serifada proporcional; trocar
// 'i' por 'W' sem travar a largura de cada caractere faz a linha inteira
// dançar. As caixas são medidas DEPOIS da fonte carregar e liberadas no fim.
//
// `prefers-reduced-motion`: NÃO PINA e não embaralha — rolagem sequestrada é
// exatamente o que essa preferência pede para não existir. A cena nasce
// montada e o que sobra de movimento é a deriva lenta do campo ambiente.
// =============================================================================

const TRACE_GLYPHS = '0123456789ABCDEF/GETPOSTU·░▒█';

export function initHeroScene({ reduced, ambient, trace, sound }) {
    const section = document.querySelector('[data-lv2-hero]');
    const headline = document.querySelector('[data-lv2-headline]');

    if (!section || !headline) return;

    const layers = {
        ambient: section.querySelector('[data-lv2-layer="ambient"]'),
        trace: section.querySelector('[data-lv2-layer="trace"]'),
        object: section.querySelector('[data-lv2-layer="object"]'),
        front: section.querySelector('[data-lv2-layer="front"]'),
    };

    const object = section.querySelector('[data-lv2-object]');
    // O tilt mora num wrapper INTERNO: a linha do tempo pinada anima o
    // objeto (entrada, escala, paralaxe) e o ponteiro anima o wrapper. Dois
    // donos na mesma propriedade brigam, e quem perde é o frame.
    const tilt = section.querySelector('[data-lv2-object-tilt]');
    const front = [...section.querySelectorAll('[data-lv2-front-item]')];

    // Fora da tela, o campo ambiente para de desenhar. Ele só existe no
    // herói; continuar pintando um canvas invisível pelas outras sete seções
    // é comer bateria de graça.
    ScrollTrigger.create({
        trigger: section,
        start: 'top bottom',
        end: 'bottom top',
        onToggle: (self) => ambient?.setRunning(self.isActive),
    });

    const fonts = document.fonts?.ready ?? Promise.resolve();

    Promise.race([fonts, new Promise((resolve) => setTimeout(resolve, 1200))]).then(() => {
        build();
        ScrollTrigger.refresh();
    });

    function build() {
        const split = new SplitText(headline, {
            type: 'chars,words',
            charsClass: 'lv2-char',
            wordsClass: 'lv2-word',
            tag: 'span',
        });

        headline.classList.add('lv2-split-ready');

        const chars = split.chars;
        const finals = chars.map((char) => char.textContent);

        if (reduced) {
            // Sem pin e sem scrub: a cena já está montada.
            gsap.to(chars, { opacity: 1, duration: 0.3, stagger: 0.005, ease: 'none' });
            gsap.set([...front, object].filter(Boolean), { opacity: 1, y: 0, scale: 1 });
            gsap.set(tilt, { rotateX: 0, rotateY: 0 });
            ambient?.setIntensity(1);

            return;
        }

        // As caixas de cada caractere são travadas para o embaralhamento e
        // liberadas no fim (ver o cabeçalho do arquivo).
        chars.forEach((char) => {
            char.style.display = 'inline-block';
            char.style.width = `${char.getBoundingClientRect().width}px`;
            char.style.textAlign = 'center';
        });

        const unlock = () =>
            chars.forEach((char) => {
                char.style.width = '';
                char.style.textAlign = '';
            });

        // =================================================================
        // INTRO — toca sozinha. O herói existe desde o primeiro paint.
        // =================================================================
        gsap.set(front, { opacity: 0, y: 26 });
        gsap.set(object, { opacity: 0, y: 70, scale: 0.94 });
        gsap.set(tilt, { rotateX: 16, rotateY: -11 });
        ambient?.setIntensity(0.35);

        const intro = gsap.timeline({ delay: 0.1, onComplete: unlock });

        // A CRISTALIZAÇÃO. As linhas entram numa velocidade absurda (24×) e
        // FREIAM até quase parar exatamente enquanto as letras assentam. Não é
        // um fade: é o rastro desabando e travando em frase, que é a imagem
        // que a página inteira está tentando defender. `expo.out` porque a
        // desaceleração precisa ser violenta no começo e longa no fim — uma
        // curva linear aqui lê como "carregando", não como "assentando".
        const crystal = { speed: 24 };

        trace?.setSpeed(crystal.speed);

        intro.to(crystal, {
            speed: 1.6,
            duration: 1.6,
            ease: 'expo.out',
            onUpdate: () => trace?.setSpeed(crystal.speed),
        }, 0);

        // O campo ambiente acende junto: o herói sai do escuro para a luz.
        intro.to({}, {
            duration: 1.6,
            ease: 'power2.out',
            onUpdate() {
                ambient?.setIntensity(0.35 + this.progress() * 0.5);
            },
        }, 0);

        intro.to(chars, {
            opacity: 1,
            duration: 0.5,
            stagger: { each: 0.022, from: 'start' },
            ease: 'expo.out',
        });

        intro.from(
            chars,
            { yPercent: 55, duration: 0.85, stagger: { each: 0.022, from: 'start' }, ease: 'expo.out' },
            0,
        );

        // A passagem pelos glifos do log: o título sai DE DENTRO do rastro.
        chars.forEach((char, index) => {
            if (finals[index].trim() === '') return;

            const at = index * 0.022;

            for (let step = 0; step < 3; step += 1) {
                intro.call(
                    (element) => {
                        element.textContent = TRACE_GLYPHS[Math.floor(Math.random() * TRACE_GLYPHS.length)];
                    },
                    [char],
                    at + step * 0.04,
                );
            }

            intro.call((element, text) => { element.textContent = text; }, [char, finals[index]], at + 0.12);
        });

        intro.to(front, { opacity: 1, y: 0, duration: 0.7, stagger: 0.08, ease: 'expo.out' }, 0.45);
        intro.to(object, { opacity: 1, y: 0, scale: 1, duration: 0.9, ease: 'expo.out' }, 0.62);
        intro.to(tilt, { rotateX: 0, rotateY: 0, duration: 1.1, ease: 'expo.out' }, 0.62);

        // =================================================================
        // CENA — pinada, dirigida pelo scroll.
        // `end` = quase duas telas no desktop; no mobile a viewport é mais
        // alta em relação ao conteúdo e prender por duas telas cansa.
        // =================================================================
        const distance = window.matchMedia('(min-width: 768px)').matches ? '+=170%' : '+=90%';

        const scene = gsap.timeline({
            scrollTrigger: {
                trigger: section,
                start: 'top top',
                end: distance,
                pin: true,
                // Sem `pinSpacing` o conteúdo seguinte sobe por baixo do
                // herói pinado e a página engasga.
                pinSpacing: true,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });

        // --- 0,00 → 0,45 · as linhas FREIAM e o campo ambiente acende -----
        scene.to({}, {
            duration: 0.45,
            ease: 'none',
            onUpdate() {
                const progress = this.progress();

                trace?.setSpeed(1.6 - progress * 1.2);
                ambient?.setIntensity(0.85 + progress * 0.15);
            },
        }, 0);

        // --- 0,20 → 1,00 · os planos se separam ---------------------------
        // Paralaxe: cada plano sai a uma velocidade diferente. É isso, e não
        // sombra, que produz a sensação de profundidade ao rolar.
        const parallax = [
            [layers.ambient, 0.06],
            [layers.trace, 0.18],
            [layers.object, 0.42],
            [layers.front, 0.85],
        ];

        parallax.forEach(([layer, factor]) => {
            if (!layer) return;

            scene.to(layer, { yPercent: -factor * 30, duration: 0.8, ease: 'none' }, 0.2);
        });

        // A instrução de rolagem some assim que a pessoa rola: ela já
        // cumpriu o trabalho dela, e um convite que insiste depois de aceito
        // vira ruído.
        const hint = section.querySelector('[data-lv2-scroll-hint]');

        if (hint) scene.to(hint, { opacity: 0, duration: 0.12, ease: 'none' }, 0);

        // O herói entrega a página: a frente sai antes do fundo.
        scene.to(layers.front, { opacity: 0.05, duration: 0.3, ease: 'power2.in' }, 0.7);
        scene.to(layers.object, { opacity: 0.08, duration: 0.3, ease: 'power2.in' }, 0.74);

        // Um pulso quando o rastro termina de frear.
        scene.call(() => sound?.sweep(), null, 0.45);
    }

    // -------------------------------------------------------------------
    // TILT do objeto pelo ponteiro. Só no ponteiro fino (mouse): num toque
    // não existe "passar por cima", e um tilt que só reage ao toque vira um
    // solavanco no meio da leitura.
    // -------------------------------------------------------------------
    if (!reduced && object && tilt && window.matchMedia('(pointer: fine)').matches) {
        const quickX = gsap.quickTo(tilt, 'rotateY', { duration: 0.6, ease: 'power3.out' });
        const quickY = gsap.quickTo(tilt, 'rotateX', { duration: 0.6, ease: 'power3.out' });

        section.addEventListener('pointermove', (event) => {
            const rect = object.getBoundingClientRect();
            const x = (event.clientX - (rect.left + rect.width / 2)) / rect.width;
            const y = (event.clientY - (rect.top + rect.height / 2)) / rect.height;

            // ±6°: o suficiente para o vidro ter volume, longe do enjoo.
            quickX(gsap.utils.clamp(-6, 6, x * 11));
            quickY(gsap.utils.clamp(-6, 6, -y * 9));
        });

        section.addEventListener('pointerleave', () => {
            quickX(0);
            quickY(0);
        });
    }
}
