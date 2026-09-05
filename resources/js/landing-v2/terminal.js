import { ScrollTrigger } from 'gsap/ScrollTrigger';

// =============================================================================
// O TERMINAL COREOGRAFADO — timing HUMANO.
//
// Um terminal que digita a uma velocidade constante é uma barra de progresso
// fantasiada, e o olho percebe a fraude em dois segundos. O que se digita
// aqui tem a cadência de alguém digitando: cada caractere leva entre 18 e 62ms,
// a barra e o traço custam mais (a mão procura a tecla), depois de um espaço
// há uma micro-pausa, e antes do Enter existe um respiro de meio segundo — o
// instante em que a pessoa relê o comando.
//
// Sem JS os três comandos já estão escritos no bloco, na ordem. O módulo só
// esvazia e reescreve quando assume, e reserva a altura antes de esvaziar
// para a seção não saltar (CLS).
// =============================================================================

const SLOW_KEYS = new Set(['/', '-', '_', '.', ':']);

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function keyDelay(character, previous) {
    if (previous === ' ') return 90;
    if (SLOW_KEYS.has(character)) return 62;
    if (character === ' ') return 55;

    return 18 + Math.random() * 34;
}

export function initTerminal(data, reduced) {
    const body = document.querySelector('[data-lv2-terminal-body]');
    const steps = data.steps ?? [];
    const stepNodes = [...document.querySelectorAll('[data-lv2-step]')];

    if (!body || steps.length === 0) return;

    body.style.minHeight = `${body.getBoundingClientRect().height}px`;
    body.textContent = '';

    let current = -1;

    // Token de execução: rolar depressa cancela a digitação em curso em vez
    // de enfileirar duas ao mesmo tempo (ou, pior, ignorar o passo novo).
    let runId = 0;

    function markStep(active) {
        stepNodes.forEach((node, i) => {
            if (i === active) node.setAttribute('aria-current', 'step');
            else node.removeAttribute('aria-current');
        });
    }

    function appendLine(className) {
        const line = document.createElement('p');

        line.className = `lv2-line ${className}`;
        body.append(line);

        return line;
    }

    function caretNode() {
        const caret = document.createElement('span');

        caret.className = 'lv2-caret';
        caret.setAttribute('aria-hidden', 'true');

        return caret;
    }

    async function run(target) {
        const id = (runId += 1);

        markStep(target);
        body.textContent = '';

        for (let i = 0; i <= target; i += 1) {
            if (id !== runId) return;

            const step = steps[i];
            const line = appendLine('text-gray-800 dark:text-gray-100');
            const prompt = document.createElement('span');

            prompt.className = 'text-text-muted';
            prompt.textContent = '$ ';
            line.append(prompt);

            const caret = caretNode();

            line.append(caret);

            if (reduced || i < target) {
                // Comandos já vistos (e o modo de movimento reduzido) entram
                // prontos: repetir a digitação do começo a cada rolagem é
                // fazer o visitante esperar por algo que ele já leu.
                line.insertBefore(document.createTextNode(step.command), caret);
            } else {
                for (let c = 0; c < step.command.length; c += 1) {
                    if (id !== runId) return;

                    line.insertBefore(document.createTextNode(step.command[c]), caret);

                    // eslint-disable-next-line no-await-in-loop
                    await wait(keyDelay(step.command[c], step.command[c - 1]));
                }

                // O respiro antes do Enter.
                // eslint-disable-next-line no-await-in-loop
                await wait(480);
            }

            if (id !== runId) return;

            caret.remove();

            const output = appendLine('text-text-muted');

            output.textContent = step.output;

            if (i < target) output.classList.add('opacity-60');
        }

        if (id !== runId) return;

        // O cursor fica piscando no fim, esperando o próximo comando.
        const idle = appendLine('text-text-muted');

        idle.textContent = '$ ';
        idle.append(caretNode());
    }

    function goTo(index) {
        if (index === current) return;

        current = index;
        run(index);
    }

    stepNodes.forEach((node, i) => {
        ScrollTrigger.create({
            trigger: node,
            start: 'top 70%',
            end: 'bottom 30%',
            onEnter: () => goTo(i),
            onEnterBack: () => goTo(i),
        });
    });

    // Se a seção já estiver na tela quando a página abre (janela alta,
    // link com âncora, recarregar no meio), nenhum onEnter dispara —
    // o primeiro passo precisa aparecer assim mesmo.
    ScrollTrigger.addEventListener('refresh', () => {
        if (current === -1 && body.getBoundingClientRect().top < window.innerHeight) goTo(0);
    });
}
