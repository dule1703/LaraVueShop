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

        // Inicijalizacija stores-a MORA biti PRE app.mount(): Vue izvršava
        // mounted hook-ove dece (Cart.vue/Checkout.vue onMounted) SINHRONO
        // unutar app.mount(), tj. PRE nego što ovaj poziv uopšte vrati kontrolu
        // ovde. Da je authStore.init() pozvan POSLE app.mount() (kao ranije),
        // cart.loadFromBackend() bi na svakom punom (ne-SPA) učitavanju video
        // authStore.user === null i ulogovanog korisnika pogrešno tretirao kao
        // gosta (učitavao praznu/zastarelu localStorage korpu umesto prave
        // korpe sa servera - otud "0,00 €" na /checkout posle punog reload-a
        // sa /cart, iako je korpa na /cart bila ispravna). props.initialPage
        // je dostupan sinhrono ovde (pre mount-a), za razliku od usePage() koji
        // čita Inertia-in interni `page` ref popunjen tek unutar App komponente.
        const authStore = useAuthStore();
        const cartStore = useCartStore();
        authStore.init(props.initialPage.props.auth?.user ?? null);

        if (authStore.user) {
            console.log('🔐 Initial load: User je ulogovan – učitavam backend korpu');
            cartStore.loadFromBackend();
        } else {
            console.log('👤 Initial load: Gost – učitavam localStorage korpu');
            cartStore.loadFromLocalStorage();
        }

        app.mount(el);

        return app;
    },

    progress: {
        color: '#4B5563',
    },
});