import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';

// Font Awesome
import { library } from '@fortawesome/fontawesome-svg-core';
import { faShoppingCart } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

library.add(faShoppingCart);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .component('font-awesome-icon', FontAwesomeIcon); // Font Awesome komponenta

        app.mount(el);

        // Globalno učitavanje korpe iz localStorage-a (jednom na startu)
        import('@/Stores/cart').then(({ useCartStore }) => {
            const cartStore = useCartStore();
            cartStore.loadFromLocalStorage();
        });

        return app;
    },
    progress: {
        color: '#4B5563',
    },
});