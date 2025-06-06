import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/Pages/Welcome.vue',
                'resources/js/Pages/Auth/Login.vue',
                'resources/js/Pages/Auth/Register.vue',
                'resources/js/Pages/Auth/ForgotPassword.vue',
                'resources/js/Pages/Auth/ResetPassword.vue',
                'resources/js/Pages/Auth/VerifyEmail.vue',
                'resources/js/Pages/Profile/Partials/DeleteUserForm.vue',
                'resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue',
                'resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue',
                'resources/js/Pages/Profile/Edit.vue',
                'resources/js/Pages/Dashboard.vue',
                'resources/js/Pages/Disclaimer.vue',
                'resources/js/Pages/Projects/Index.vue',
                'resources/js/Pages/Projects/Create.vue',
                'resources/js/Pages/Projects/Edit.vue',
                'resources/js/Pages/Scenes/Edit.vue',
                'resources/js/Pages/Scenes/Create.vue',
                'resources/js/Pages/Scenes/Index.vue',
                'resources/js/Pages/Characters/Index.vue',
                'resources/js/Pages/Characters/Create.vue',
                'resources/js/Pages/Characters/Edit.vue',
                'resources/js/Pages/Items/Index.vue',
                'resources/js/Pages/Items/Create.vue',
                'resources/js/Pages/Items/Edit.vue',
                'resources/js/Pages/Scenes.vue',
                'resources/js/Pages/Projects.vue'
            ],
            refresh: true
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
    build: {
        manifest: true,
        outDir: 'public/build',
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['vue', '@inertiajs/vue3'],
                }
            }
        },
        chunkSizeWarningLimit: 1000,
        minify: 'esbuild',
        target: 'esnext',
        assetsDir: '',
        emptyOutDir: true
    }
});
