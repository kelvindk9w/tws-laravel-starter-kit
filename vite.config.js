import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // filament.css = tema do /admin (mesmos tokens de theme.css) —
            // apontado por ->viteTheme() no AdminPanelProvider.
            input: ['resources/css/app.css', 'resources/css/filament.css', 'resources/js/app.js'],
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
