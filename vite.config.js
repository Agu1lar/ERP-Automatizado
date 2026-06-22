import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        transformer: 'postcss',
        postcss: {
            plugins: [tailwindcss(), autoprefixer()],
        },
    },
    build: {
        cssMinify: false,
    },
});
