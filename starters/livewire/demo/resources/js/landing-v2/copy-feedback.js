/**
 * Confirmação VISÍVEL da cópia do comando no herói.
 *
 * A cópia em si é do kit (`[data-copy]` em resources/js/ui.js): troca o
 * rótulo do botão e dispara o #clipboard-toast. O que falta ali é uma
 * confirmação no lugar onde o olho já está — logo abaixo do botão —, porque
 * o rótulo que muda dentro do botão principal é justamente o texto que o
 * cursor está cobrindo no momento do clique.
 */
export function initCopyFeedback(data) {
    const button = document.querySelector('[data-lv2-clone]');
    const feedback = document.querySelector('[data-lv2-clone-feedback]');
    const strings = data.i18n ?? {};

    if (!button || !feedback) return;

    let timer;

    button.addEventListener('click', () => {
        clearTimeout(timer);

        feedback.textContent = strings.cloneCopied ?? '';
        feedback.classList.add('text-(--lv2-amber-ink)');

        timer = setTimeout(() => {
            feedback.textContent = strings.cloneCopy ?? '';
            feedback.classList.remove('text-(--lv2-amber-ink)');
        }, 2400);
    });
}
