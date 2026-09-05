// =============================================================================
// PLANO DE FUNDO — campo ambiente gerado PROCEDURALMENTE (canvas 2D).
//
// Não é imagem: são quatro manchas radiais de baixa frequência que orbitam
// devagar (period de 40 a 90 segundos) sobre a cor de fundo do tema, mais um
// grão fino sobreposto. Uma imagem estática de gradiente pesa mais, não
// responde à troca de tema e denuncia o template; um campo gerado responde
// aos tokens e nunca se repete.
//
// Por que 2D e não WebGL: quatro `createRadialGradient` por frame numa tela
// de 1/2 resolução custam menos de 1ms num notebook comum, e sem WebGL a CSP
// da rota não precisa de `worker-src` nem de `blob:`.
//
// Orçamento: canvas renderizado a 0,5x (o gradiente é suave, ninguém vê a
// diferença), grão pré-desenhado UMA vez num canvas offscreen e reaplicado
// como padrão — gerar ruído por frame é o erro clássico que derruba o fps.
// =============================================================================

// Duas manchas de âmbar e duas neutras. As âmbar são DISCRETAS de propósito:
// o mundo é tinta, e o âmbar é ponto de luz — o acento. Uma mancha âmbar forte
// tinge a página inteira de sépia e mata justamente o preto que faz as linhas
// de log brilharem. Aqui o fundo é o palco, não o espetáculo.
const BLOBS = [
    { hue: 'accent', radius: 0.55, speed: 0.016, phase: 0.0, alpha: 0.2 },
    { hue: 'accent', radius: 0.42, speed: -0.011, phase: 2.1, alpha: 0.13 },
    { hue: 'ink', radius: 0.6, speed: 0.008, phase: 4.0, alpha: 0.34 },
    { hue: 'ink', radius: 0.38, speed: -0.02, phase: 5.4, alpha: 0.24 },
];

/**
 * @param {boolean} reduced  preferência de movimento reduzido
 * @param {string}  selector qual campo montar (herói ou CTA final)
 * @param {number}  pace     multiplicador de ritmo — o campo do CTA final
 *                           roda mais devagar de propósito: ali a trilha
 *                           está terminando, não começando.
 */
export function initAmbient(reduced, selector = '[data-lv2-ambient]', pace = 1) {
    const canvas = document.querySelector(selector);

    if (!canvas) return null;

    const context = canvas.getContext('2d', { alpha: true });

    if (!context) return null;

    // Metade da resolução: o conteúdo é de baixíssima frequência espacial.
    const SCALE = 0.5;

    let width = 0;
    let height = 0;
    let grain = null;
    let running = true;
    let intensity = 1;

    // Teto de 30fps para o campo ambiente. O conteúdo dele muda em dezenas de
    // SEGUNDOS: redesenhar quatro gradientes radiais 60 vezes por segundo é
    // gastar metade do orçamento de frame com uma diferença que ninguém vê —
    // e essa metade faz falta ao herói pinado, que roda no mesmo frame.
    const FRAME_BUDGET = 1000 / 30;
    let lastDraw = 0;

    // O ritmo é por instância: BLOBS é uma constante compartilhada entre os
    // dois campos (herói e CTA final), então a velocidade efetiva é calculada
    // no frame — mutar a constante contaminaria o outro campo.
    const rate = (reduced ? 0.15 : 1) * pace;

    function palette() {
        const computed = getComputedStyle(document.documentElement);

        return {
            accent: computed.getPropertyValue('--lv2-amber').trim() || '#ffb000',
            ink: computed.getPropertyValue('--lv2-hairline-strong').trim() || '#3a3a40',
        };
    }

    let colors = palette();

    new MutationObserver(() => { colors = palette(); }).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    /** Grão: desenhado UMA vez, reaplicado como padrão. */
    function buildGrain() {
        const tile = document.createElement('canvas');

        tile.width = 128;
        tile.height = 128;

        const tileContext = tile.getContext('2d');
        const image = tileContext.createImageData(128, 128);

        for (let i = 0; i < image.data.length; i += 4) {
            const value = 128 + (Math.random() - 0.5) * 255;

            image.data[i] = value;
            image.data[i + 1] = value;
            image.data[i + 2] = value;
            image.data[i + 3] = 12;
        }

        tileContext.putImageData(image, 0, 0);

        return context.createPattern(tile, 'repeat');
    }

    function resize() {
        const rect = canvas.getBoundingClientRect();

        width = Math.max(1, Math.round(rect.width * SCALE));
        height = Math.max(1, Math.round(rect.height * SCALE));

        canvas.width = width;
        canvas.height = height;

        grain = buildGrain();
    }

    function frame(now) {
        if (running && now - lastDraw >= FRAME_BUDGET) {
            lastDraw = now;
            const time = now / 1000;

            context.clearRect(0, 0, width, height);

            for (const blob of BLOBS) {
                const angle = time * blob.speed * rate + blob.phase;
                const x = width * (0.5 + Math.cos(angle) * 0.34);
                const y = height * (0.5 + Math.sin(angle * 0.8) * 0.3);
                const radius = Math.max(width, height) * blob.radius;

                const gradient = context.createRadialGradient(x, y, 0, x, y, radius);
                const color = colors[blob.hue];

                gradient.addColorStop(0, hexToRgba(color, blob.alpha * intensity));
                gradient.addColorStop(1, hexToRgba(color, 0));

                context.fillStyle = gradient;
                context.fillRect(0, 0, width, height);
            }

            if (grain) {
                context.fillStyle = grain;
                context.fillRect(0, 0, width, height);
            }
        }

        requestAnimationFrame(frame);
    }

    resize();

    // Movimento reduzido: o campo continua existindo, quase parado (ver
    // `rate`). Um fundo congelado tira a única textura que a página tem; o
    // que se corta é a velocidade, não a matéria.

    requestAnimationFrame(frame);

    let timer;

    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(resize, 150);
    });

    return {
        /** O herói pinado modula a intensidade conforme a cena avança. */
        setIntensity(value) { intensity = value; },
        setRunning(value) { running = value; },
    };
}

function hexToRgba(hex, alpha) {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
    const value = Number.parseInt(full, 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}
