import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // filament.css = tema do /admin (mesmos tokens de theme.css) —
            // apontado por ->viteTheme() no AdminPanelProvider.
            input: [
                'resources/css/app.css',
                'resources/css/filament.css',
                'resources/js/app.js',
                // Landing "O Rastro" (/v2) — bundle próprio: só essa rota
                // carrega GSAP, Lenis e a fonte display (Instrument Serif).
                'resources/css/landing-v2.css',
                'resources/js/landing-v2.js',
                // Landing oficial "Céu" (/) — bundle próprio: GSAP/Lenis e o
                // Three.js entram por import() dinâmico, em pedaços à parte.
                // Nenhuma outra tela do produto baixa um byte disso.
                'resources/css/landing.css',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
