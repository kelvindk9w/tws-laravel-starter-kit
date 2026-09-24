// =============================================================================
// A TEXTURA SONORA — "terminal/pulso", gerada pela Web Audio API.
//
// Sem arquivo externo, sem request, sem CDN: cada som é sintetizado no
// navegador. Um clique é um ruído branco de 12ms passado por um passa-banda
// agudo (a batida seca da tecla) somado a um seno grave curtíssimo (o corpo
// do teclado); a redação tem um som próprio, uma varredura descendente —
// porque o que acontece ali é diferente do que acontece no fundo, e som que
// não distingue eventos vira ruído de fundo.
//
// MUDO POR PADRÃO, sempre. Som que começa sozinho é o pior comportamento que
// uma página pode ter, e o AudioContext só é criado no clique do botão —
// que é também o gesto que os navegadores exigem para permitir áudio.
//
// A preferência NÃO é persistida de propósito: guardá-la significaria que
// numa próxima visita a página já sabe que pode fazer barulho, e o primeiro
// gesto qualquer (um clique num link) viraria som que ninguém pediu naquela
// sessão. Mudo a cada visita é a única regra que nunca surpreende.
// =============================================================================

export function initSound(data) {
    const buttons = [...document.querySelectorAll('[data-lv2-sound]')];
    const strings = data.i18n ?? {};

    if (buttons.length === 0) return null;

    let context = null;
    let master = null;
    let enabled = false;
    let lastTick = 0;

    function ensureContext() {
        if (context) return context;

        const Ctor = window.AudioContext ?? window.webkitAudioContext;

        if (!Ctor) return null;

        context = new Ctor();
        master = context.createGain();
        // Volume de teclado mecânico ouvido de outra sala.
        master.gain.value = 0.055;
        master.connect(context.destination);

        return context;
    }

    function noiseBuffer(seconds) {
        const length = Math.floor(context.sampleRate * seconds);
        const buffer = context.createBuffer(1, length, context.sampleRate);
        const channel = buffer.getChannelData(0);

        for (let i = 0; i < length; i += 1) channel[i] = Math.random() * 2 - 1;

        return buffer;
    }

    /** Clique seco: uma linha de log nasceu. */
    function tick() {
        if (!enabled || !context || context.state !== 'running') return;

        // Trava de cadência: várias linhas podem nascer no mesmo frame, e
        // dez cliques simultâneos não são um pulso, são um estalo.
        const now = context.currentTime;

        if (now - lastTick < 0.07) return;

        lastTick = now;

        const source = context.createBufferSource();

        source.buffer = noiseBuffer(0.012);

        const band = context.createBiquadFilter();

        band.type = 'bandpass';
        band.frequency.value = 1800 + Math.random() * 900;
        band.Q.value = 1.4;

        const gain = context.createGain();

        gain.gain.setValueAtTime(0.9, now);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.05);

        source.connect(band).connect(gain).connect(master);
        source.start(now);
        source.stop(now + 0.06);

        // O corpo grave do toque.
        const body = context.createOscillator();
        const bodyGain = context.createGain();

        body.type = 'sine';
        body.frequency.setValueAtTime(120 + Math.random() * 30, now);
        bodyGain.gain.setValueAtTime(0.35, now);
        bodyGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.09);

        body.connect(bodyGain).connect(master);
        body.start(now);
        body.stop(now + 0.1);
    }

    /** Varredura descendente: a redação passou por um campo. */
    function sweep() {
        if (!enabled || !context || context.state !== 'running') return;

        const now = context.currentTime;
        const source = context.createBufferSource();

        source.buffer = noiseBuffer(0.5);

        const filter = context.createBiquadFilter();

        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(5200, now);
        filter.frequency.exponentialRampToValueAtTime(320, now + 0.45);
        filter.Q.value = 6;

        const gain = context.createGain();

        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.5, now + 0.05);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);

        source.connect(filter).connect(gain).connect(master);
        source.start(now);
        source.stop(now + 0.52);
    }

    function paint() {
        buttons.forEach((button) => {
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            button.title = enabled ? strings.soundOn ?? '' : strings.soundOff ?? '';
        });
    }

    function toggle() {
        enabled = !enabled;

        if (enabled) {
            const ctx = ensureContext();

            // O gesto do clique é o que autoriza o áudio: sem isto o
            // contexto nasce suspenso e nada toca. `resume()` é assíncrono —
            // o primeiro clique só soa depois que ele resolve, e esse
            // primeiro clique é a confirmação de que o botão funcionou.
            Promise.resolve(ctx?.resume?.()).then(tick).catch(() => {});
        }

        paint();
    }

    buttons.forEach((button) => button.addEventListener('click', toggle));

    // Estado inicial: mudo, com o rótulo dizendo o que o clique vai fazer.
    paint();

    return { tick, sweep, get enabled() { return enabled; } };
}
