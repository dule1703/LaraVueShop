import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createApp, h, onMounted, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import { useAuthStore } from './auth';
import { useCartStore } from './cart';
import { __setInertiaPageForTest } from '@inertiajs/vue3';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

// usePage() u pravoj aplikaciji čita Inertia-in modul-level `page` ref, koji
// popunjava Inertia-ina App komponenta u SVOM setup()-u (tokom app.mount()).
// Ranija verzija ovog mock-a je vraćala fiksan `{ props: { auth: {} } }` -
// to je SAKRILO pravi bag (watch()-ov getter puca kad je `page.props`
// undefined, vidi auth.js) jer `.props` kod tog mock-a nikad nije bio
// undefined. Ovaj mock umesto toga koristi pravi Vue `ref()`, počinje kao
// undefined (tačno stanje pre app.mount()-a) i `__setInertiaPageForTest()`
// simulira trenutak kad Inertia-ina App komponenta popuni page tokom
// app.mount()-a (posle authStore.init() poziva u app.js).
vi.mock('@inertiajs/vue3', async () => {
  const { ref: vueRef } = await import('vue');
  const pageRef = vueRef(undefined);
  return {
    usePage: () => ({
      get props() {
        return pageRef.value?.props;
      },
    }),
    __setInertiaPageForTest: (page) => {
      pageRef.value = page;
    },
  };
});

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

describe('REGRESIJA (naknadni bag u samom PR #52 rešenju): watch() unutar init() pre app.mount()-a', () => {
  it('BAG - dokumentuje zašto je stari mock ovaj bag sakrio: page.props ovde počinje kao undefined (kao u pravoj Inertia-i), ne kao fiksan objekat', () => {
    // Pre bilo kakvog __setInertiaPageForTest() poziva, page.props je
    // undefined - tačno stanje u pravom browseru pre app.mount()-a. Ako bi
    // watch()-ov getter bio stari `page.props.auth?.user` (bez `?.` posle
    // `props`), ovo bi bacilo "Cannot read properties of undefined
    // (reading 'auth')" - identično stack trace-u iz pravog browsera
    // (headless Chrome + CDP, potvrđeno ručno pri istrazi ovog bug-a).
    const page = { get props() { return undefined; } };
    expect(() => page.props.auth?.user).toThrow(/Cannot read propert/);
    // Isti izraz sa `?.` posle `props` (ispravka u auth.js) ne baca:
    expect(() => page.props?.auth?.user).not.toThrow();
  });

  it('authStore.init() ne sme da baca dok Inertia još nije postavila page.props (pravi uzrok bele strane iz bug reporta)', () => {
    // Ovo je TAČNO ono što app.js radi: authStore.init() se zove PRE
    // app.mount()-a, tj. pre nego što Inertia-ina App komponenta ikad
    // postavi svoj page ref. Stari getter (`page.props.auth?.user`, bez
    // `?.` posle `props`) je ovde bacao sinhrono - taj throw izlazi iz
    // watch()-a (nema error-boundary komponente van app.mount()-a) i
    // zaustavlja ceo createInertiaApp setup() PRE poziva app.mount(el),
    // otud bela strana i "page.props is undefined" iz izveštaja.
    expect(() => useAuthStore().init(LOGGED_IN_USER)).not.toThrow();
  });

  it('hidratacija (Inertia kasnije popuni page.props tokom app.mount()-a) se NE sme protumačiti kao LOGIN za već poznatog korisnika', async () => {
    const authStore = useAuthStore();
    const cart = useCartStore();

    // Kao u app.js: init() PRE app.mount()-a, pa odmah zatim eksplicitno
    // učitavanje korpe za ulogovanog korisnika (authStore.user je već
    // ispravno initialUser, ne čeka se watch).
    authStore.init(LOGGED_IN_USER);
    await cart.loadFromBackend();

    const mergeSpy = vi.spyOn(cart, 'mergeGuestCartOnLogin');
    const loadSpy = vi.spyOn(cart, 'loadFromBackend');

    // Simulacija onoga što se dešava TOKOM app.mount(): Inertia-ina App
    // komponenta popuni page.props sa ISTIM korisnikom - običan pun
    // reload, ne stvaran login.
    __setInertiaPageForTest({ props: { auth: { user: LOGGED_IN_USER } } });
    await nextTick();
    await nextTick();

    // Da watch koristi svoj interni (Vue watch()) "oldValue" umesto
    // this.previousUserId, ovo bi lažno izgledalo kao null -> user (LOGIN)
    // i ponovo pozvalo loadFromBackend/mergeGuestCartOnLogin - nepotrebno
    // (duplo gađanje /api/cart) ili, gore, pogrešno mergovalo zaostalu
    // gost-korpu iz localStorage-a u nalog već ulogovanog korisnika na
    // svakom punom reload-u. Reprodukovano i potvrđeno u pravom browseru
    // (headless Chrome + CDP, autentikovana sesija) pre ove ispravke.
    expect(mergeSpy).not.toHaveBeenCalled();
    expect(loadSpy).not.toHaveBeenCalled();
    expect(authStore.user?.id).toBe(LOGGED_IN_USER.id);
  });
});
