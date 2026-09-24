/**
 * CONFIRMAÇÃO DE AÇÃO SENSÍVEL — simulação declarada.
 *
 * O fluxo real do kit (senha de transação + código por e-mail → token de uso
 * único, com rate limit) roda no servidor e não pode ser disparado de uma
 * landing pública. Então esta demo NÃO finge ser produção: o texto na tela
 * diz que nenhum e-mail é enviado e mostra o código a usar. Uma demo que
 * finge ser real corrói exatamente a confiança que a página inteira está
 * tentando construir.
 *
 * O que ela prova de verdade é o resto: os componentes (<x-input>,
 * <x-button>, <x-alert>) são os do kit, o erro nomeia o problema e a
 * recuperação, e as tentativas restantes aparecem — como no fluxo real.
 */
export function initTwoFactorDemo(data) {
    const root = document.querySelector('[data-lv2-twofa]');

    if (!root) return;

    const strings = data.proof ?? {};
    const sendButton = root.querySelector('[data-lv2-twofa-send]');
    const sendLabel = root.querySelector('[data-lv2-twofa-send-label]');
    const feedback = root.querySelector('[data-lv2-twofa-feedback]');
    const form = root.querySelector('[data-lv2-twofa-form]');
    const input = root.querySelector('[data-lv2-twofa-input]');
    const confirmButton = root.querySelector('[data-lv2-twofa-confirm]');
    const resetButton = root.querySelector('[data-lv2-twofa-reset]');

    let code = null;
    let attempts = 3;

    // Os alertas são os do kit; o markup é o mesmo do <x-alert>, montado aqui
    // porque o tipo muda em runtime.
    const ALERT_STYLES = {
        info: 'border-sky-500/50 bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
        success: 'border-green-500/50 bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-300',
        error: 'border-red-500/50 bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300',
    };

    function say(type, message) {
        feedback.hidden = false;
        feedback.className = `flex gap-3 rounded-lg border p-4 text-sm ${ALERT_STYLES[type]}`;
        feedback.setAttribute('role', 'alert');
        feedback.textContent = message;
    }

    function reset() {
        code = null;
        attempts = 3;
        form.hidden = true;
        feedback.hidden = true;
        feedback.textContent = '';
        input.value = '';
        if (sendLabel) sendLabel.textContent = strings.send ?? '';
        sendButton.disabled = false;
    }

    sendButton?.addEventListener('click', () => {
        sendButton.disabled = true;
        if (sendLabel) sendLabel.textContent = strings.sending ?? '';

        // A espera existe porque o fluxo real também espera: um código que
        // aparece no mesmo instante do clique não parece um e-mail.
        setTimeout(() => {
            code = String(Math.floor(100000 + Math.random() * 900000));
            attempts = 3;

            say('info', (strings.sent ?? '').replace(':code', code));

            form.hidden = false;
            sendButton.disabled = false;
            if (sendLabel) sendLabel.textContent = strings.send ?? '';
            input.focus();
        }, 900);
    });

    confirmButton?.addEventListener('click', () => {
        if (code === null) return;

        if (input.value.trim() === code) {
            say('success', strings.ok ?? '');
            form.hidden = true;

            return;
        }

        attempts -= 1;

        if (attempts <= 0) {
            say('error', (strings.error ?? '').replace(':attempts', '0'));
            form.hidden = true;

            return;
        }

        say('error', (strings.error ?? '').replace(':attempts', String(attempts)));
        input.focus();
        input.select();
    });

    resetButton?.addEventListener('click', reset);
}
