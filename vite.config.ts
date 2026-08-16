import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

/**
 * Un téléphone du réseau local ne résout ni `argent.test` ni son certificat : avec `VITE_LAN_HOST`,
 * le serveur de développement s'annonce en clair sur l'IP de la machine au lieu du domaine de Herd.
 */
const lanHost = process.env.VITE_LAN_HOST;

const privateNetworkOrigins = [
    /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/,
    /^https?:\/\/.*\.test(:\d+)?$/,
    /^https?:\/\/10\.\d+\.\d+\.\d+(:\d+)?$/,
    /^https?:\/\/192\.168\.\d+\.\d+(:\d+)?$/,
    /^https?:\/\/172\.(1[6-9]|2\d|3[01])\.\d+\.\d+(:\d+)?$/,
];

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            detectTls: lanHost ? false : null,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        ...(lanHost
            ? {
                  host: '0.0.0.0',
                  hmr: { host: lanHost },
                  cors: { origin: privateNetworkOrigins },
              }
            : {}),
    },
});
