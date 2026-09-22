import { defineConfig } from 'vite';
import path from 'node:path';

// Odvojeno od vite.config.js: laravel-vite-plugin (koji inače daje '@' alias,
// vidi CLAUDE.md #9) se ovde ne pokreće, pa alias mora eksplicitno.
export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
    },
});
