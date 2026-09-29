import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import HeaderSearch from './HeaderSearch.vue';

// `usePage().url` mora biti čitljivo u trenutku mount-a (searchParamFromUrl()
// se izvršava sinhrono u setup()-u) - `state.url` se postavlja PRE mount()-a
// u svakom testu. `router.get` je obican vi.fn() spy, ne treba mu prava
// Inertia navigacija da bi se proverili prosleđeni parametri.
const { state } = vi.hoisted(() => ({ state: { url: '/shop' } }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: state.url }),
    router: { get: vi.fn() },
}));

// route() u pravoj aplikaciji (Ziggy) ima dva oblika: route() bez argumenata
// vraća objekat sa .current(name), route(name) vraća generisan URL/string.
// HeaderSearch.vue koristi oba oblika, ali OBA poziva su u <script setup>
// JS kodu (submit()), ne u <template>-u - kompajliraju se kao obična
// referenca na identifikator `route`, NE kao `_ctx.route` (to bi važilo
// samo za poziv unutar template-a). Ziggy-jev Vue plugin registruje `route`
// samo kao app.config.globalProperties/provide (vidi
// vendor/tightenco/ziggy/dist/index.esm.js) - to ovde ne pomaže jer se ne
// mount-uje pravi app sa tim pluginom. U pravom browseru `route` ipak
// postoji kao pravi `window.route` jer ga Laravel-ova `@routes` Blade
// direktiva (resources/views/app.blade.php) ubacuje kao poseban <script>
// PRE Vue-a, nezavisno od Vue plugin-a - zato bare `route()` pozivi iz
// <script setup> koda rade u produkciji (isti obrazac kao Shop.vue apply()).
// Test zato mora postaviti pravi `globalThis.route`, ne `global.mocks`.
function makeRouteMock(currentRouteName) {
    return (name) => {
        if (name === undefined) {
            return { current: (checkName) => checkName === currentRouteName };
        }
        return name;
    };
}

function mountSearch(url, currentRouteName) {
    state.url = url;
    globalThis.route = makeRouteMock(currentRouteName);
    return mount(HeaderSearch, {
        props: { inputClass: '', iconClass: '' },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('HeaderSearch', () => {
    it('inicijalna vrednost polja dolazi iz ?search= parametra trenutnog URL-a', () => {
        const wrapper = mountSearch('/shop?search=andric&category=romani', 'shop');

        expect(wrapper.find('input').element.value).toBe('andric');
    });

    it('polje je prazno kad URL nema ?search= parametar', () => {
        const wrapper = mountSearch('/shop?category=romani', 'shop');

        expect(wrapper.find('input').element.value).toBe('');
    });

    it('Enter na /shop šalje na "shop" rutu i ČUVA ostale aktivne filtere', async () => {
        const wrapper = mountSearch('/shop?category=romani&price_min=5', 'shop');

        await wrapper.find('input').setValue('seobe');
        await wrapper.find('input').trigger('keyup.enter');

        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params] = router.get.mock.calls[0];
        expect(url).toBe('shop');
        expect(params).toEqual({ category: 'romani', price_min: '5', search: 'seobe' });
    });

    it('Enter na / (home) takođe čuva aktivne filtere (isti katalog kao /shop)', async () => {
        const wrapper = mountSearch('/?format=paperback', 'home');

        await wrapper.find('input').setValue('pekic');
        await wrapper.find('input').trigger('keyup.enter');

        const [, params] = router.get.mock.calls[0];
        expect(params).toEqual({ format: 'paperback', search: 'pekic' });
    });

    it('Enter NA DRUGOJ stranici (npr. /cart) NE prenosi query string te stranice', async () => {
        const wrapper = mountSearch('/cart?foo=bar', 'cart');

        await wrapper.find('input').setValue('seobe');
        await wrapper.find('input').trigger('keyup.enter');

        const [, params] = router.get.mock.calls[0];
        expect(params).toEqual({ search: 'seobe' });
    });

    it('prazna (ili samo razmaci) vrednost uklanja ?search= iz parametara, ne šalje prazan string', async () => {
        const wrapper = mountSearch('/shop?search=stari-upit&category=romani', 'shop');

        await wrapper.find('input').setValue('   ');
        await wrapper.find('input').trigger('keyup.enter');

        const [, params] = router.get.mock.calls[0];
        expect(params).toEqual({ category: 'romani' });
        expect(params.search).toBeUndefined();
    });

    it('submit uvek uklanja ?page= (nova pretraga vraća na prvu stranu)', async () => {
        const wrapper = mountSearch('/shop?page=3&category=romani', 'shop');

        await wrapper.find('input').setValue('seobe');
        await wrapper.find('input').trigger('keyup.enter');

        const [, params] = router.get.mock.calls[0];
        expect(params.page).toBeUndefined();
        expect(params).toEqual({ category: 'romani', search: 'seobe' });
    });

    it('X dugme je vidljivo samo kad polje ima tekst', async () => {
        const wrapper = mountSearch('/shop?category=romani', 'shop');

        expect(wrapper.find('button[aria-label="Obriši pretragu"]').exists()).toBe(false);

        await wrapper.find('input').setValue('seobe');
        expect(wrapper.find('button[aria-label="Obriši pretragu"]').exists()).toBe(true);
    });

    it('X dugme prazni polje BEZ navigacije kad search nije bio aktivan URL filter', async () => {
        const wrapper = mountSearch('/shop?category=romani', 'shop');

        await wrapper.find('input').setValue('seobe');
        await wrapper.find('button[aria-label="Obriši pretragu"]').trigger('click');

        expect(wrapper.find('input').element.value).toBe('');
        expect(router.get).not.toHaveBeenCalled();
    });

    it('X dugme prazni polje I navigira na shop BEZ ?search=, čuvajući ostale filtere, kad je search bio aktivan URL filter', async () => {
        const wrapper = mountSearch('/shop?search=andric&category=romani', 'shop');

        await wrapper.find('button[aria-label="Obriši pretragu"]').trigger('click');

        expect(wrapper.find('input').element.value).toBe('');
        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params] = router.get.mock.calls[0];
        expect(url).toBe('shop');
        expect(params).toEqual({ category: 'romani' });
        expect(params.search).toBeUndefined();
    });
});
