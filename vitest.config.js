import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

// Odvojeno od vite.config.js: laravel-vite-plugin (koji inače daje '@' alias,
// vidi CLAUDE.md #9) se ovde ne pokreće, pa alias mora eksplicitno. `vue()`
// plugin dodat kad su prvi put testirane .vue SFC komponente (@vue/test-utils,
// BookCoverPlaceholder/BookCard) - bez njega vitest ne zna da kompajlira .vue
// import, samo plain .js (kao dosadašnji auth.test.js/cart.test.js).
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
    },
});
