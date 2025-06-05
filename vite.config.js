import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import glob from 'glob';

// Trova tutti i file .vue nella directory Pages
const vueFiles = glob.sync('resources/js/Pages/**/*.vue');

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                ...vueFiles
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
