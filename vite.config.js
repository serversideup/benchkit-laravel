import fs from 'fs';
import { fileURLToPath } from 'node:url';
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
    resolve: {
        alias: {
            // Rules both this app and the results gallery have to agree on —
            // what makes a run clean, and why one isn't. Shared as source
            // rather than copied, the way docs/shared/submission already is,
            // because two copies of a rule is two answers to one question.
            '@shared': fileURLToPath(new URL('./docs/shared', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: process.env.VITE_DOMAIN || 'vite.dev.test',
            clientPort: 443,
        },
        // The dev server runs from the repository root, and chokidar's
        // default ignores don't cover these trees. Watching them exhausts
        // the host's inotify watcher limit, and when that happens Vite
        // crashes, the hot file disappears, and the app silently serves
        // whatever stale production build is in public/build.
        watch: {
            // docs/ is ignored wholesale to stay under the host's inotify
            // limit, but docs/shared is imported by this app — without the
            // carve-out an edit there needs a manual refresh to show up.
            ignored: [
                '**/vendor/**',
                '**/storage/**',
                '**/docs/!(shared)/**',
                '**/results/**',
                '**/.infrastructure/**',
            ],
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
