import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Standalone rather than layered on vite.config.js: the Laravel plugin and
// the HMR certificates have no place in a unit test run.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@shared': fileURLToPath(new URL('./docs/shared', import.meta.url)),
        },
    },
    test: {
        include: ['resources/js/**/*.test.js'],
    },
});
