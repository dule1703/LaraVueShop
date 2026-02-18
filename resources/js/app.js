// resources/js/app.js
import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { useAuthStore } from '@/Stores/auth';
import { useCartStore } from '@/Stores/cart';

import { library } from '@fortawesome/fontawesome-svg-core';
import { faShoppingCart } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

library.add(faShoppingCart);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pinia = createPinia();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),

    setup: ({ el, App, props, plugin }) => {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .component('font-awesome-icon', FontAwesomeIcon);

        app.mount(el);

        // Inicijalizacija stores-a NAKON mount-a
        const authStore = useAuthStore();
        const cartStore = useCartStore();

        // Inicijalizuj auth store (pokreće watch za promene korisnika)
        authStore.init();

        // Učitaj korpu na početku (guest ili auth)
        if (authStore.user) {
            console.log('🔐 Initial load: User je ulogovan – učitavam backend korpu');
            cartStore.loadFromBackend();
        } else {
            console.log('👤 Initial load: Gost – učitavam localStorage korpu');
            cartStore.loadFromLocalStorage();
        }

        return app;
    },

    progress: {
        color: '#4B5563',
    },
});