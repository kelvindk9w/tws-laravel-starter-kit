// =============================================================================
// Assets da DEMONSTRAÇÃO do kit no build do Vite do aplicativo.
//
// O vite.config.js do starter carrega este arquivo SÓ quando o pacote está
// instalado (vendor/twstec/kit-demo — no ambiente de desenvolvimento, pelo
// require-dev). Sem a demo — `composer install --no-dev`, a imagem de
// produção, ou depois de `composer remove --dev twstec/kit-demo` — o build
// não conhece nada daqui: nenhuma entrada, nenhuma imagem, nenhuma classe do
// Tailwind que só a demo usa.
//
// O que a demo acrescenta:
//
// 1. ENTRADAS: os bundles das duas landings (CSS e JS de cada uma) e
//    resources/js/images.js, que só existe para as imagens da landing
//    entrarem no manifesto (as views as pedem por Vite::asset()).
//
// 2. FONTES DO TAILWIND: o Tailwind do aplicativo só enxerga o que está no
//    projeto (o vendor/ fica de fora da detecção automática). As views, o JS
//    e as classes PHP da demo usam utilitários do tema do kit; as linhas
//    `@source` com as pastas do pacote são acrescentadas ao app.css (site) e
//    ao filament.css (as telas da demo no /admin) na hora do build — o CSS do
//    produto não cita a demo.
//
// 3. `resolve.preserveSymlinks`: no desenvolvimento o pacote é um LINK
//    (path repository do Composer). Sem isto, o Vite seguiria o link até
//    packages/demo e procuraria gsap, lenis e three a partir de lá, fora do
//    node_modules do aplicativo; com isto, os arquivos continuam sendo
//    vendor/twstec/kit-demo/… — inclusive no manifesto, que é o caminho que as
//    views pedem.
//
// 4. Nome das imagens com `img/landing/` no caminho (build/assets/img/landing/
//    <nome>-<hash>.webp), como eram servidas antes de irem para o pacote.
// =============================================================================

const PACKAGE = 'vendor/twstec/kit-demo';

/** Pastas do pacote que o Tailwind precisa ler (as mesmas que o projeto lia quando a demo morava nele). */
const SOURCES = ['src', 'config', 'database', 'lang', 'resources', 'routes'];

/** Onde cada folha de estilo do aplicativo passa a ler a demo. */
const STYLESHEETS = {
    'resources/css/app.css': SOURCES,
    'resources/css/filament.css': ['src/Filament'],
};

const IMAGES = '/resources/img/landing/';

export default function kitDemo() {
    return {
        input: [
            // Landing alternativa "O Rastro" (/v2) — bundle próprio: só essa
            // rota carrega GSAP, Lenis e a fonte display (Instrument Serif).
            `${PACKAGE}/resources/css/landing-v2.css`,
            `${PACKAGE}/resources/js/landing-v2.js`,
            // Landing oficial "Céu" (/) — bundle próprio: GSAP/Lenis e o
            // Three.js entram por import() dinâmico, em pedaços à parte.
            // Nenhuma outra tela do produto baixa um byte disso.
            `${PACKAGE}/resources/css/landing.css`,
            `${PACKAGE}/resources/js/landing.js`,
            // Imagens da landing e da vitrine (Vite::asset).
            `${PACKAGE}/resources/js/images.js`,
        ],
        plugins: [
            {
                name: 'twstec-kit-demo',
                enforce: 'pre',
                config() {
                    return {
                        resolve: { preserveSymlinks: true },
                        build: {
                            rollupOptions: {
                                output: {
                                    assetFileNames: (asset) => {
                                        const origem = [...(asset.originalFileNames ?? []), asset.originalFileName ?? ''];

                                        return origem.some((arquivo) => arquivo.includes(IMAGES))
                                            ? 'assets/img/landing/[name]-[hash][extname]'
                                            : 'assets/[name]-[hash][extname]';
                                    },
                                },
                            },
                        },
                    };
                },
                transform(code, id) {
                    const arquivo = id.split('?')[0];

                    for (const [stylesheet, pastas] of Object.entries(STYLESHEETS)) {
                        if (arquivo.endsWith(`/${stylesheet}`) && !arquivo.includes(`/${PACKAGE}/`)) {
                            const linhas = pastas.map((pasta) => `@source '../../${PACKAGE}/${pasta}';`);

                            return { code: `${code}\n${linhas.join('\n')}\n`, map: null };
                        }
                    }

                    return null;
                },
            },
        ],
    };
}
