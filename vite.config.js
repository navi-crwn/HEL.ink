import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('/node_modules/axios/')) return 'http';
                    if (id.includes('/node_modules/alpinejs/') || id.includes('/node_modules/@alpinejs/')) return 'alpine';
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/home.css', 'resources/css/coss.css', 'resources/css/coss-home.css', 'resources/js/home.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
