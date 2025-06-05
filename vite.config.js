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
                'resources/js/Pages/Dashboard.vue',
                'resources/js/Pages/Disclaimer.vue',
                'resources/js/Pages/Projects/Index.vue'
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
