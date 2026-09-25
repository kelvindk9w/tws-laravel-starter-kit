// =============================================================================
// O MUNDO PADRÃO DA /v2 É A TINTA.
//
// "O Rastro" é um log de auditoria: preto tinta, off-white e âmbar de
// terminal. O mundo papel existe e é bonito, mas o conceito nasceu no escuro,
// e é no escuro que as linhas de log são luminosas em vez de cinzentas.
//
// A REGRA, e o que ela respeita:
//
//   localStorage 'light'  → PAPEL.  O visitante escolheu claro, e uma
//                           escolha explícita vale mais do que a direção de
//                           arte de uma página.
//   localStorage 'dark'   → TINTA.
//   'system' ou nada      → TINTA.  Aqui "sistema" resolve contra o padrão
//                           DA TELA, não contra o sistema operacional — é a
//                           mesma cadeia que o kit já usa (localStorage →
//                           data-theme-default → 'system'), com a /v2
//                           declarando o próprio `data-theme-default`.
//
// Nada é escrito em localStorage: a /v2 não sequestra a preferência do
// visitante para o resto do site. Ela só decide o que fazer na ausência de
// uma escolha.
//
// Por que existe um GUARDA aqui, além do `data-theme-default="dark"` que o
// Blade já manda: o `resources/js/ui.js` do kit reaplica o tema no load, na
// troca de preferência do SO e a cada clique no seletor. Quando o valor
// guardado é literalmente 'system', ele resolve contra o SO e apagaria a
// classe `.dark` num sistema operacional claro. O guarda roda depois dele e
// devolve o mundo da página — sem tocar em mais nada.
// =============================================================================

function storedTheme() {
    try {
        return localStorage.getItem('theme');
    } catch {
        // Modo privado ou storage bloqueado: sem escolha guardada = tinta.
        return null;
    }
}

export function initThemeWorld() {
    const root = document.documentElement;

    const apply = () => {
        // A ÚNICA escolha que tira a página da tinta é 'light', explícito.
        if (storedTheme() === 'light') return;

        root.classList.add('dark');
    };

    apply();

    // Depois de qualquer ação do kit que reaplique o tema. `requestAnimationFrame`
    // porque o handler do ui.js roda no mesmo clique — o guarda precisa ser o
    // último a falar.
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-theme-set], [data-theme-toggle]')) {
            requestAnimationFrame(apply);
        }
    });

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => requestAnimationFrame(apply));
}
