import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createApp, h, onMounted } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import { useAuthStore } from './auth';
import { useCartStore } from './cart';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

// usePage() u pravoj aplikaciji čita Inertia-in modul-level `page` ref, koji
// popunjava Inertia-ina App komponenta u SVOM setup()-u (tokom app.mount()).
// Pod vitest-om nijedna prava Inertia App komponenta se ne mount-uje, pa bi
// stvarni usePage() vratio props === undefined. Ovde mockujemo samo da watch()
// unutar authStore.init() ima šta da čita bez pucanja - sam sadržaj (auth.user)
// namerno ostaje prazan, jer se u ispravci authStore.user više NE čita odavde,
// nego iz initialUser parametra (vidi auth.js).
vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: {} } }),
}));

const SERVER_PRODUCT = { id: 43, name: 'Prava knjiga iz baze', price: 20.99, image: 'x.jpg', stock: 5, available: true };
const LOGGED_IN_USER = { id: 7, first_name: 'Pera' };

beforeEach(() => {
  setActivePinia(createPinia());
  localStorage.clear();
  vi.clearAllMocks();
  globalThis.route = (name) => name;

  axios.get.mockImplementation((url) => {
    if (url === 'api.cart.show') {
      // Prava korpa ulogovanog korisnika na serveru: 1 knjiga, količina 5.
      return Promise.resolve({ data: { cart: { items: [{ product_id: 43, quantity: 5 }] } } });
    }
    if (url === 'api.cart.products') {
      return Promise.resolve({ data: { products: [SERVER_PRODUCT] } });
    }
    return Promise.reject(new Error('unexpected axios.get url: ' + url));
  });
});

/**
 * Mount-uje minimalnu komponentu čiji onMounted radi TAČNO ono što
 * Cart.vue/Checkout.vue rade (vidi resources/js/Pages/Checkout.vue):
 *   await cart.loadFromBackend();
 *   await cart.hydrate();
 * Vue izvršava mounted hook-ove dece SINHRONO unutar app.mount() (root-level
 * mount, bez async komponenti) - to je uzrok reprodukovanog race-a ispod.
 */
function mountCheckoutLikePage(cart, onDone) {
  const CheckoutLike = {
    setup() {
      onMounted(async () => {
        await cart.loadFromBackend();
        await cart.hydrate();
        onDone();
      });
      return () => h('div');
    },
  };

  const el = document.createElement('div');
  document.body.appendChild(el);
  return createApp({ render: () => h(CheckoutLike) }).mount(el);
}

describe('auth + cart boot redosled: puni (ne-SPA) reload na /checkout za ulogovanog korisnika', () => {
  it('BAG (stari app.js redosled): authStore.init() posle app.mount() - Checkout vidi authStore.user===null, korpa/cena ostaju na 0', async () => {
    const authStore = useAuthStore();
    const cart = useCartStore();
    let mounted = false;

    // Stari (bagovan) app.js: mount PA TEK ONDA authStore.init().
    mountCheckoutLikePage(cart, () => { mounted = true; });
    // U ovom trenutku je Checkout-ov onMounted već sinhrono pozvao
    // cart.loadFromBackend(), koji je video authStore.user === null
    // (default state - init() se još nije izvršio) i pogrešno uzeo GOST granu.
    authStore.init(LOGGED_IN_USER);

    await vi.waitFor(() => expect(mounted).toBe(true));
    // Dokumentuje TAČNO prijavljeni bag: cena/total ostaju 0 iako je korisnik
    // ulogovan i njegova prava korpa na serveru ima 1 knjigu x 5 komada.
    expect(cart.totalAmount).toBe(0);
    expect(cart.items).toEqual([]);
  });

  it('ISPRAVLJENO (app.js sada zove authStore.init() PRE app.mount()): cena na Checkout-u nije 0', async () => {
    const authStore = useAuthStore();
    const cart = useCartStore();
    let mounted = false;

    // Ispravljen app.js redosled: authStore.init() PRE app.mount().
    authStore.init(LOGGED_IN_USER);
    mountCheckoutLikePage(cart, () => { mounted = true; });

    await vi.waitFor(() => expect(mounted).toBe(true));

    expect(cart.items).toEqual([{ product_id: 43, quantity: 5 }]);
    expect(cart.productDetails[43]?.price).toBe(20.99);
    // 20.99 x 5, NIKAD 0 - ovo je scenario iz bug reporta (/cart ispravan,
    // /checkout posle punog reload-a prikazivao 0,00 €).
    expect(cart.totalAmount).toBeCloseTo(104.95);
  });
});
