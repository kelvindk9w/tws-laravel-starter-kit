/**
 * Contagem de estrelas do repositório — a ÚNICA chamada externa da página.
 *
 * Fallback silencioso por decisão: se a chamada falhar (offline, rate limit
 * anônimo do GitHub, ambiente com CSP mais fechada, repositório privado), o
 * bloco simplesmente não aparece. Nunca um "0", nunca uma mensagem de erro —
 * um número que pode mentir sobre a tração de um projeto é pior do que a
 * ausência dele, e um erro de rede não é assunto do visitante.
 *
 * A CSP da /v2 libera api.github.com no connect-src, e só isso
 * (config/security.php → content_security_policy_landing_alt).
 */
export function initStars(data) {
    const wrapper = document.querySelector('[data-lv2-stars]');
    const value = document.querySelector('[data-lv2-stars-value]');
    const repo = data.repo;

    if (!wrapper || !value || !repo) return;

    const match = /github\.com\/([^/]+)\/([^/#?]+)/i.exec(repo);

    if (!match) return;

    const slug = `${match[1]}/${match[2].replace(/\.git$/, '')}`;

    // AbortController: uma landing não pode ficar com uma requisição pendurada
    // se a rede engasgar.
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 4000);

    fetch(`https://api.github.com/repos/${slug}`, {
        signal: controller.signal,
        headers: { Accept: 'application/vnd.github+json' },
    })
        .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
        .then((payload) => {
            const count = Number(payload?.stargazers_count);

            // Zero não é um número para exibir: "0 estrelas" numa landing
            // diz menos do que não dizer nada.
            if (!Number.isFinite(count) || count < 1) return;

            value.textContent = new Intl.NumberFormat(document.documentElement.lang).format(count);
            wrapper.hidden = false;
        })
        .catch(() => {
            // Silêncio, de propósito.
        })
        .finally(() => clearTimeout(timeout));
}
