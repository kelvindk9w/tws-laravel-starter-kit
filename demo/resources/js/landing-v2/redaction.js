import { gsap } from 'gsap';

// =============================================================================
// MOMENTO-ASSINATURA — a redação LGPD acontecendo na frente do visitante.
//
// Coreografia, campo a campo:
//
//   1. uma varredura âmbar de 1 caractere de largura corre o valor da
//      esquerda para a direita;
//   2. ATRÁS dela o valor já foi trocado por blocos █ — o estado transitório,
//      o instante em que o dado ainda existia e deixou de existir;
//   3. quando a varredura chega ao fim, os blocos assentam no valor REDIGIDO
//      de verdade (o que o Redactor do kit devolveu no servidor).
//
// Por que não parar nos ████: parar ali seria terminar numa ilustração de
// censura. O produto não apaga o dado — ele guarda o FATO sem o segredo, e a
// diferença entre `[REDACTED]`, `m***@exemplo.com` e `472.***.***-15` é
// exatamente a tese da página. Os blocos são a transição; a verdade é o fim.
//
// Acessibilidade: dispara no hover, no foco (Tab) e no toque; o estado é
// anunciado por aria-live e a legenda de cada regra aparece junto. Um efeito
// que só existe para quem tem mouse não é um efeito, é um privilégio.
// =============================================================================

const BLOCK = '█';

export function initRedaction(data, reduced, pulse) {
    const cards = [...document.querySelectorAll('[data-lv2-audit]')];
    const strings = data.i18n ?? {};

    if (cards.length === 0) return;

    const timelines = new WeakMap();

    function build(card) {
        const fields = [...card.querySelectorAll('[data-lv2-field]')];
        const state = card.querySelector('[data-lv2-audit-state]');
        const hint = card.querySelector('[data-lv2-audit-hint]');

        const timeline = gsap.timeline({
            paused: true,
            onStart: () => {
                card.classList.add('is-redacted');
                if (hint) hint.hidden = true;
                if (state) state.textContent = strings.redacting ?? '';
                pulse?.sweep();
            },
            onComplete: () => {
                if (state) state.textContent = strings.redacted ?? '';
            },
        });

        fields.forEach((field, index) => {
            const value = field.querySelector('[data-lv2-value]');
            const sweep = field.querySelector('.lv2-sweep');
            const raw = field.dataset.raw ?? '';
            const redacted = field.dataset.redacted ?? '';

            // Campo que o Redactor não mudou não participa: animar o que
            // não mudou faria a página dizer que mudou.
            if (raw === redacted) return;

            const at = index * (reduced ? 0.04 : 0.11);
            const duration = reduced ? 0.2 : 0.42;

            // A varredura: um traço âmbar que percorre a largura do valor.
            timeline.fromTo(
                sweep,
                { width: 0, left: 0 },
                { width: '100%', duration, ease: 'power2.inOut' },
                at,
            );

            timeline.to(sweep, { width: 0, left: '100%', duration: duration * 0.6, ease: 'power2.in' }, at + duration);

            // Atrás da varredura, o valor vira blocos, caractere a caractere.
            if (!reduced) {
                const steps = Math.max(raw.length, 6);

                for (let i = 1; i <= steps; i += 1) {
                    timeline.call(
                        (node, count) => {
                            node.firstChild.textContent = `"${BLOCK.repeat(count)}${raw.slice(count)}"`;
                        },
                        [value, Math.round((i / steps) * raw.length)],
                        at + (i / steps) * duration,
                    );
                }
            }

            // E assenta na saída REAL do Redactor.
            timeline.call(
                (node, text) => { node.firstChild.textContent = `"${text}"`; },
                [value, redacted],
                at + duration + duration * 0.45,
            );

            timeline.call((node) => node.classList.add('is-redacted'), [field], at + duration);
        });

        // A volta ao original (botão "Ver o original") NÃO roda a animação de
        // trás para frente: reescreve os valores de uma vez. Uma redação que
        // "descensura" em câmera lenta contaria a história ao contrário — e a
        // história desta seção é que o dado sai e não volta.
        const restore = () => {
            // pause(0, true) volta ao início SEM redisparar os callbacks.
            timeline.pause(0, true);
            card.classList.remove('is-redacted');

            fields.forEach((field) => {
                field.querySelector('[data-lv2-value]').firstChild.textContent = `"${field.dataset.raw ?? ''}"`;
                field.classList.remove('is-redacted');
            });

            if (state) state.textContent = '';

            if (hint) {
                hint.hidden = false;
                state?.append(hint);
            }
        };

        return { timeline, restore };
    }

    function play(card) {
        if (!timelines.has(card)) timelines.set(card, build(card));

        const { timeline } = timelines.get(card);

        // Uma vez redigido, fica redigido: repetir a varredura a cada
        // passada do cursor viraria um piscar de anúncio.
        if (timeline.progress() === 0) timeline.play();
    }

    function reset(card) {
        timelines.get(card)?.restore();
    }

    cards.forEach((card) => {
        card.addEventListener('pointerenter', () => play(card));
        card.addEventListener('focus', () => play(card));
        card.addEventListener('click', () => play(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                play(card);
            }
        });
    });

    document.querySelector('[data-lv2-audit-reset]')?.addEventListener('click', () => {
        cards.forEach(reset);
    });
}
