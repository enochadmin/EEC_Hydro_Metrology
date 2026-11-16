import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        // Bind to IPv4 explicitly: the default `localhost` binding produced a
        // http://[::1]:5173 hot file that some clients cannot reach.
        host: '127.0.0.1',
        watch: {
            // Workbook uploads and compiled output churn constantly; watching
            // them made the dev server re-transform modules until it stalled.
            ignored: ['**/storage/**', '**/vendor/**', '**/public/build/**', '**/node_modules/**'],
        },
    },
});
