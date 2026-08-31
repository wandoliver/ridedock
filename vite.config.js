import { existsSync, readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const localCertificate = '.certs/localhost.pem';
const localPrivateKey = '.certs/localhost-key.pem';
const localHttpsEnabled = existsSync(localCertificate) && existsSync(localPrivateKey);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        https: localHttpsEnabled
            ? {
                cert: readFileSync(localCertificate),
                key: readFileSync(localPrivateKey),
            }
            : undefined,
        hmr: localHttpsEnabled
            ? {
                host: '127.0.0.1',
                protocol: 'wss',
            }
            : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
