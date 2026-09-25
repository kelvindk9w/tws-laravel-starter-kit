import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { formatTraceLine, randomBetween } from './context.js';

// =============================================================================
// O RASTRO — as linhas do log descendo pelo herói.
//
// Este é o primeiro segundo da página, e ele tem que ser LEGÍVEL de relance:
// não é uma textura de fundo, é o produto se mostrando. As linhas são âmbar
// de terminal, luminosas, e a profundidade é dita por três coisas ao mesmo
// tempo — opacidade, velocidade e brilho:
//
//   plano da frente   alpha 0,55–0,70 · desce rápido · com glow
//   plano do meio     alpha ~0,35     · velocidade média
//   plano do fundo    alpha até 0,15  · quase parado · sem glow
//
// E o rastro RESPONDE ao ponteiro: uma linha que passa a menos de 120px do
// cursor ACENDE (ganha alpha e glow, proporcional à distância). O visitante
// descobre isso sem instrução nenhuma, e é o que transforma um fundo bonito
// numa coisa viva.
//
// Canvas 2D, não WebGL. O que se desenha é TEXTO monoespaçado — o que o 2D
// faz melhor e mais barato — e sem WebGL a CSP da rota não precisa de
// `worker-src` nem de `blob:`.
//
// ORÇAMENTO DE FRAME (o glow é caro, e é aí que esse efeito costuma morrer):
// `shadowBlur` só é ligado nas linhas que estão realmente acesas — as do
// plano de trás desenham sem sombra nenhuma. O grão é um padrão desenhado
// UMA vez e reaplicado. O canvas para de desenhar quando o herói sai da tela.
// =============================================================================

const MAX_LINES = 34;
const FONT_SIZE = 12;
const LINE_HEIGHT = 26;

// Raio em que o ponteiro acende uma linha.
const POINTER_RADIUS = 120;

export function initTraceCanvas(data, reduced, pulse) {
    const canvas = document.querySelector('[data-lv2-canvas]');
    const entries = data.trace ?? [];

    if (!canvas || entries.length === 0) return null;

    const context = canvas.getContext('2d', { alpha: true });

    if (!context) return null;

    const lines = [];
    let width = 0;
    let height = 0;
    let dpr = 1;
    let grain = null;

    let speed = 1;
    let running = true;
    let lastFrame = performance.now();

    // Posição do ponteiro em coordenadas do canvas (-1 = fora).
    let pointerX = -1;
    let pointerY = -1;

    const texts = entries.map(formatTraceLine);

    function styles() {
        const computed = getComputedStyle(document.documentElement);

        return {
            // A COR do rastro vem do tema: âmbar-luz na tinta, âmbar-tinta no
            // papel (ver --lv2-trace-color em landing-v2.css).
            amber: computed.getPropertyValue('--lv2-trace-color').trim()
                || computed.getPropertyValue('--lv2-amber').trim()
                || '#ffb000',
            alpha: Number.parseFloat(computed.getPropertyValue('--lv2-trace-alpha')) || 0.45,
            // Brilho sobre off-white não é luz, é borrão: no papel o glow é 0.
            glow: Number.parseFloat(computed.getPropertyValue('--lv2-trace-glow')) || 0,
        };
    }

    let palette = styles();

    // O tema pode mudar no meio da página (a seção "Não acredite: mexa" é
    // literalmente isso): as cores são relidas quando a classe .dark entra
    // ou sai do <html>.
    new MutationObserver(() => { palette = styles(); }).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    /** Grão fino: desenhado UMA vez e reaplicado como padrão. */
    function buildGrain() {
        const tile = document.createElement('canvas');

        tile.width = 96;
        tile.height = 96;

        const tileContext = tile.getContext('2d');
        const image = tileContext.createImageData(96, 96);

        for (let i = 0; i < image.data.length; i += 4) {
            const value = 128 + (Math.random() - 0.5) * 255;

            image.data[i] = value;
            image.data[i + 1] = value;
            image.data[i + 2] = value;
            image.data[i + 3] = 10;
        }

        tileContext.putImageData(image, 0, 0);

        return context.createPattern(tile, 'repeat');
    }

    function resize() {
        const rect = canvas.getBoundingClientRect();

        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = rect.width;
        height = rect.height;

        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);

        context.setTransform(dpr, 0, 0, dpr, 0, 0);
        context.font = `${FONT_SIZE}px ui-monospace, "SF Mono", "JetBrains Mono", monospace`;
        context.textBaseline = 'top';

        grain = buildGrain();
    }

    function spawn(initial = false) {
        const index = Math.floor(Math.random() * texts.length);
        const entry = entries[index];

        // A profundidade é sorteada uma vez e manda em tudo: quem está à
        // frente é mais opaco, mais rápido e brilha; quem está atrás é quase
        // um sussurro. Três sinais dizendo a mesma coisa é o que faz o olho
        // ler PROFUNDIDADE em vez de "opacidades aleatórias".
        const depth = Math.random();

        lines.push({
            text: texts[index],
            // Ataques e erros são as linhas que o produto quer que você note.
            hot: entry.status === 'BLOQUEADA' || entry.status === 'ERRO',
            depth,
            x: randomBetween(-40, Math.max(60, width - 200)),
            y: initial ? randomBetween(-height, height) : -LINE_HEIGHT,
            velocity: 16 + depth * 58,
            alpha: 0.15 + depth * 0.55,
            // Quanto esta linha está acesa pelo ponteiro (0..1), suavizado.
            lit: 0,
        });

        if (!initial) pulse?.tick();
    }

    function frame(now) {
        const delta = Math.min((now - lastFrame) / 1000, 0.05);

        lastFrame = now;

        if (running) {
            context.clearRect(0, 0, width, height);

            for (let i = lines.length - 1; i >= 0; i -= 1) {
                const line = lines[i];

                line.y += line.velocity * speed * delta;

                if (line.y > height + LINE_HEIGHT) {
                    lines.splice(i, 1);

                    continue;
                }

                // --- O ponteiro acende o que passa perto ------------------
                let target = 0;

                if (pointerX >= 0) {
                    // Distância do ponteiro ao SEGMENTO da linha (ela é larga
                    // e baixa: medir até o ponto inicial acenderia a linha
                    // errada).
                    const textWidth = line.text.length * FONT_SIZE * 0.6;
                    const nearestX = Math.min(Math.max(pointerX, line.x), line.x + textWidth);
                    const dx = pointerX - nearestX;
                    const dy = pointerY - (line.y + FONT_SIZE / 2);
                    const distance = Math.hypot(dx, dy);

                    if (distance < POINTER_RADIUS) target = 1 - distance / POINTER_RADIUS;
                }

                // Suavização exponencial: acender é rápido, apagar é lento —
                // o rastro fica atrás do cursor, que é o nome da página.
                line.lit += (target - line.lit) * (target > line.lit ? 0.25 : 0.06);

                const lit = line.lit;
                const alpha = Math.min(1, (line.alpha + lit * 0.45) * palette.alpha);

                context.globalAlpha = alpha;
                context.fillStyle = palette.amber;

                // Glow SÓ onde ele conta: linha da frente, linha quente ou
                // linha acesa pelo ponteiro. Ligar shadowBlur nas 34 linhas
                // é o que derruba o fps deste efeito.
                const glow = palette.glow * (lit * 16 + (line.depth > 0.72 ? 7 : 0) + (line.hot ? 6 : 0));

                if (glow > 0.5) {
                    context.shadowColor = palette.amber;
                    context.shadowBlur = glow;
                } else {
                    context.shadowBlur = 0;
                }

                context.fillText(line.text, line.x, line.y);
            }

            context.shadowBlur = 0;
            context.globalAlpha = 1;

            if (grain) {
                context.fillStyle = grain;
                context.fillRect(0, 0, width, height);
            }

            while (lines.length < MAX_LINES) spawn();
        }

        requestAnimationFrame(frame);
    }

    resize();

    for (let i = 0; i < MAX_LINES; i += 1) spawn(true);

    // Movimento reduzido: as linhas continuam existindo e descendo — em
    // marcha lenta. Congelar o rastro tiraria a única coisa que explica o
    // conceito da página; o que se corta é a velocidade, não a ideia.
    if (reduced) speed = 0.22;

    requestAnimationFrame(frame);

    // Fora da tela, o loop para de desenhar. Uma landing que continua
    // pintando um canvas invisível é uma landing que come bateria de graça.
    ScrollTrigger.create({
        trigger: canvas.closest('[data-lv2-hero]') ?? canvas.closest('section'),
        start: 'top bottom',
        end: 'bottom top',
        onToggle: (self) => { running = self.isActive; },
    });

    // O ponteiro só acende no dispositivo que TEM ponteiro: num toque não
    // existe "passar perto", e um brilho que segue o dedo esconde justamente
    // o que o dedo está tentando ler.
    if (window.matchMedia('(pointer: fine)').matches) {
        const hero = canvas.closest('[data-lv2-hero]') ?? document.body;

        hero.addEventListener('pointermove', (event) => {
            const rect = canvas.getBoundingClientRect();

            pointerX = event.clientX - rect.left;
            pointerY = event.clientY - rect.top;
        });

        hero.addEventListener('pointerleave', () => {
            pointerX = -1;
            pointerY = -1;
        });
    }

    document.addEventListener('visibilitychange', () => {
        lastFrame = performance.now();
    });

    let resizeTimer;

    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resize, 150);
    });

    // A VELOCIDADE é dirigida de fora: a intro do herói faz as linhas
    // CRISTALIZAREM (de muito rápidas até quase paradas, enquanto o título
    // se forma) e a cena pinada continua freando conforme se rola.
    return {
        setSpeed(value) { if (!reduced) speed = Math.max(0.15, value); },
        setRunning(value) { running = value; },
    };
}
