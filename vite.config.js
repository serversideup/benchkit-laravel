import fs from 'fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'vite.dev.test',
            clientPort: 443,
        },
        https: {
            key: fs.readFileSync('/usr/src/app/.infrastructure/conf/traefik/dev/certificates/local-dev-key.pem'),
            cert: fs.readFileSync('/usr/src/app/.infrastructure/conf/traefik/dev/certificates/local-dev.pem'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
