import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: process.env.ASSET_URL ? `${process.env.ASSET_URL.replace(/\/$/, '')}/build/` : '/build/',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: process.env.VITE_DEV_HOST ?? '127.0.0.1',
        hmr: process.env.VITE_DEV_SERVER_URL
            ? {
                host: new URL(process.env.VITE_DEV_SERVER_URL).hostname,
                protocol: new URL(process.env.VITE_DEV_SERVER_URL).protocol.replace(':', '') === 'https' ? 'wss' : 'ws',
                clientPort: Number(new URL(process.env.VITE_DEV_SERVER_URL).port || (new URL(process.env.VITE_DEV_SERVER_URL).protocol === 'https:' ? 443 : 80)),
              }
            : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
