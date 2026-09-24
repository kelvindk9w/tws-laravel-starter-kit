// Contexto compartilhado da /v2: os dados que o Blade publicou e a
// preferência de movimento do sistema.

/**
 * Lê o <script type="application/json" id="lv2-data">.
 *
 * O bloco não executa (é um tipo desconhecido para o navegador) e por isso
 * continua coberto pela CSP estrita do kit — é transporte de dados, não
 * script inline. Se ele sumir ou vier quebrado, a página segue: cada módulo
 * checa o que precisa antes de assumir o controle.
 */
export function readPageData() {
    const node = document.getElementById('lv2-data');

    if (!node) return {};

    try {
        return JSON.parse(node.textContent) ?? {};
    } catch {
        return {};
    }
}

/**
 * Preferência de movimento reduzido. Na /v2 isso significa MENOS movimento
 * (distâncias curtas, sem embaralhar caracteres, canvas em marcha lenta),
 * nunca uma página parada: um documento que não responde ao scroll é uma
 * regressão de usabilidade, não uma acomodação.
 */
export function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Sorteio determinístico o bastante para textura visual. */
export function randomBetween(min, max) {
    return min + Math.random() * (max - min);
}

/** Formata uma linha do log no MESMO formato da tabela request_logs. */
export function formatTraceLine(entry) {
    const method = entry.method.padEnd(6, ' ');
    const endpoint = entry.endpoint.padEnd(34, ' ').slice(0, 34);
    const http = String(entry.http).padStart(3, ' ');
    const ms = `${entry.ms}ms`.padStart(7, ' ');

    return `${entry.id}  ${method}${endpoint}${http}${ms}  ${entry.status}${
        entry.attack ? `  attack=${entry.attack}` : ''
    }`;
}
