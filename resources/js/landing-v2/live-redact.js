// =============================================================================
// O HERÓI RESPONDE A QUEM CHEGOU — digite qualquer coisa e veja a redação.
//
// O visitante escreve num campo e o que ele escreveu aparece imediatamente
// numa LINHA DE LOG, já passada pelas regras de redação do kit. É o mesmo
// argumento do resto da página, só que com o dado da própria pessoa: dizer
// "seus dados são redigidos" é marketing; mostrar o CPF dela virando
// `472.***.***-15` enquanto ela digita é prova.
//
// HONESTIDADE DAS REGRAS. As três primeiras são EXATAMENTE as do
// App\Core\Logging\Redactor::redactString (mesmas expressões, mesma máscara):
// CNPJ, CPF e e-mail. A quarta — `chave: valor` sensível — é a regra
// isSensitiveKey() do mesmo Redactor, aplicada ao par que a pessoa digitar.
// A quinta (número de cartão solto em texto livre) é a ÚNICA extensão da
// demo: no kit, cartão é mascarado pelo NOME do campo (`card_number`), e num
// texto livre não existe nome de campo — a demo aplica a mesma máscara ao
// número reconhecido, e a legenda diz isso.
//
// Nada é enviado a lugar nenhum: a redação roda inteira no navegador, o campo
// não tem `name` e não existe formulário em volta.
// =============================================================================

const MASK = '[REDACTED]';

// Chaves sensíveis do Redactor (const SENSITIVE_KEYS) + os sufixos.
const SENSITIVE_KEYS = [
    'password', 'password_confirmation', 'current_password', 'transaction_password',
    'senha', 'senha_confirmacao', 'senha_atual', 'senha_transacao',
    'token', 'access_token', 'refresh_token', 'id_token', 'api_key', 'api_secret',
    'secret', 'client_secret', 'authorization', 'private_key', 'webhook_secret',
    'card_number', 'card_cvv', 'cvv', 'cvc', 'card_expiry',
    'code', 'verification_code', 'codigo', 'codigo_verificacao',
];

const SENSITIVE_SUFFIXES = ['_token', '_secret', '_password', '_api_key'];

/** Mantém os 3 primeiros e 2 últimos dígitos, preservando a pontuação. */
function maskDocument(value) {
    const total = (value.match(/\d/g) ?? []).length;
    let index = 0;

    return value.replace(/\d/g, (digit) => {
        const position = index++;

        return position < 3 || position >= total - 2 ? digit : '*';
    });
}

function isSensitiveKey(key) {
    const lower = key.toLowerCase();

    return SENSITIVE_KEYS.includes(lower) || SENSITIVE_SUFFIXES.some((suffix) => lower.endsWith(suffix));
}

/**
 * Aplica as regras e devolve o texto redigido + quais regras agiram.
 *
 * @returns {{ text: string, rules: string[] }}
 */
export function redact(input) {
    const rules = new Set();
    let text = input;

    // Par `chave: valor` sensível — antes de tudo, porque o valor pode ser
    // um e-mail ou um documento e a chave manda.
    text = text.replace(/([A-Za-z_][A-Za-z0-9_]*)(\s*[:=]\s*)("?)([^\s",;]+)\3/g, (match, key, sep, quote, value) => {
        if (!isSensitiveKey(key)) return match;

        rules.add('key');

        return `${key}${sep}${quote}${MASK}${quote}`;
    });

    // CNPJ antes do CPF (14 dígitos conteriam um CPF no meio).
    text = text.replace(/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/g, (match) => {
        rules.add('document');

        return maskDocument(match);
    });

    text = text.replace(/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/g, (match) => {
        rules.add('document');

        return maskDocument(match);
    });

    // Cartão: a extensão declarada da demo (ver o cabeçalho do arquivo).
    text = text.replace(/\b(?:\d[ -]?){13,19}\b/g, (match) => {
        if ((match.match(/\d/g) ?? []).length < 13) return match;

        rules.add('card');

        return match.replace(/\d/g, (digit, index, whole) => {
            const digits = whole.replace(/\D/g, '').length;
            const position = whole.slice(0, index).replace(/\D/g, '').length;

            return position >= digits - 4 ? digit : '*';
        });
    });

    text = text.replace(/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*(@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/g, (match, first, domain) => {
        rules.add('email');

        return `${first}***${domain}`;
    });

    return { text, rules: [...rules] };
}

export function initLiveRedact(data) {
    const input = document.querySelector('[data-lv2-live-input]');
    const output = document.querySelector('[data-lv2-live-output]');
    const note = document.querySelector('[data-lv2-live-note]');

    if (!input || !output) return;

    const strings = data.i18n ?? {};
    const rules = data.rules ?? {};

    function render() {
        const raw = input.value;

        if (raw.trim() === '') {
            output.textContent = strings.liveEmpty ?? '';
            output.classList.remove('is-redacted');
            if (note) note.textContent = '';

            return;
        }

        const { text, rules: applied } = redact(raw);
        const changed = text !== raw;

        output.textContent = text;
        output.classList.toggle('is-redacted', changed);

        if (note) {
            note.textContent = changed
                ? `${strings.liveRedacted} · ${applied.map((rule) => rules[rule] ?? rule).join(' · ')}`
                : strings.liveClean ?? '';
        }
    }

    input.addEventListener('input', render);
    render();
}
