import fs from 'fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// The Spin dev environment serves HMR over https via Traefik's local
// certificates; environments without them (CI, plain `npm run build`)
// fall back to Vite's defaults.
const certificatePath = '.infrastructure/conf/traefik/dev/certificates';
const hasLocalCertificates = fs.existsSync(`${certificatePath}/local-dev.pem`);

export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: {
            host: process.env.VITE_DOMAIN || 'vite.dev.test',
            clientPort: 443,
        },
        https: hasLocalCertificates ? {
            key: fs.readFileSync(`${certificatePath}/local-dev-key.pem`),
            cert: fs.readFileSync(`${certificatePath}/local-dev.pem`),
        } : undefined,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
