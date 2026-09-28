import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import Shop from './Shop.vue';

// Head/Link su jednostavni stub-ovi (dovoljno da template kompajlira i
// renderuje slot/href); router.get/router.on su pravi vi.fn() spy-ovi -
// Shop.vue ih zove direktno iz <script setup> JS koda (ne samo template-a),
// pa mora ići preko vi.mock (isti razlog kao HeaderSearch.test.js).
vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div style="display:none" />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { get: vi.fn(), on: vi.fn(() => () => {}) },
}));

const EMPTY_FILTERS = {
    category: null, author: null, publisher: null, language: null, script: null,
    format: null, price_min: null, price_max: null, in_stock: false, search: null,
};

const OPTIONS = {
    categories: [{ id: 1, slug: 'romani', name: 'Romani', depth: 0 }],
    authors: [{ id: 1, slug: 'andric', name: 'Ivo Andrić' }],
    publishers: [{ id: 1, slug: 'vulkan', name: 'Vulkan' }],
    languages: ['sr'],
    scripts: ['Latn', 'Cyrl'],
    formats: ['paperback', 'hardcover'],
};

function mountShop(filtersOverride = {}) {
    return mount(Shop, {
        props: {
            books: { data: [], links: [], total: 0 },
            filters: { ...EMPTY_FILTERS, ...filtersOverride },
            options: OPTIONS,
        },
        global: {
            stubs: {
                AuthenticatedLayout: { template: '<div><slot /></div>' },
                BookCard: true,
                Pagination: true,
            },
            mocks: { route: (name) => name }, // <Link :href="route('shop')"> u template-u
        },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    globalThis.route = (name) => name; // route('shop') pozvan iz <script setup> JS koda
    // jsdom ne implementira matchMedia - Shop.vue ga zove u onMounted() za
    // isDesktopFilters (Teleport :disabled).
    window.matchMedia = vi.fn().mockImplementation((query) => ({
        matches: true,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    }));
});

describe('Shop.vue — regresija fix/search-sync (DEO A: search živi samo u props.filters)', () => {
    it('lokalna promena filtera ČUVA search iz props.filters (nema race, "mirno" stanje)', async () => {
        const wrapper = mountShop({ category: 'romani' });

        // Simulira ono što HeaderSearch.vue-ova navigacija radi kad legne:
        // Inertia ažurira props ove stranice.
        await wrapper.setProps({ filters: { ...EMPTY_FILTERS, category: 'romani', search: 'seobe' } });
        await nextTick();

        // VTU-ov setValue() na <select> već interno dispatch-uje 'change'
        // (tako ažurira v-model) - dodatni .trigger('change') bi duplirao
        // event i apply() pozvao dva puta.
        await wrapper.find('#f-format').setValue('paperback');

        expect(router.get).toHaveBeenCalledTimes(1);
        const [url, params] = router.get.mock.calls[0];
        expect(url).toBe('shop');
        expect(params).toEqual({ category: 'romani', format: 'paperback', search: 'seobe' });
    });

    it('apply() ČEKA da se završi navigacija u letu (npr. header pretraga) pre slanja, pa uzima najsvežiji search', async () => {
        let startCb;
        let finishCb;
        router.on.mockImplementation((event, cb) => {
            if (event === 'start') startCb = cb;
            if (event === 'finish') finishCb = cb;
            return () => {};
        });

        const wrapper = mountShop({ category: 'romani' });

        // Header pretraga (HeaderSearch.vue, potpuno odvojena komponenta)
        // pokreće SVOJU router.get() navigaciju - globalni 'start' event.
        startCb();

        // Korisnik menja lokalni filter DOK je ta navigacija još u letu.
        await wrapper.find('#f-format').setValue('paperback');

        // apply() se NIJE poslao odmah - zakazan je (pendingReapply), inače bi
        // stigao sa zastarelim (praznim) search-om i pregazio header pretragu
        // (tačno bag iz fix/search-sync PR-a).
        expect(router.get).not.toHaveBeenCalled();

        // Header pretraga legne: Inertia ažurira props (search stiže), pa
        // GLOBALNI 'finish' event. NAPOMENA: `format` namerno NIJE u ovom
        // simuliranom odgovoru - header pretraga ga nikad nije ni slala
        // (poznato, dokumentovano rezidualno ograničenje u CLAUDE.md: lokalni
        // filter promenjen DOK je header pretraga u letu može privremeno biti
        // vraćen na podrazumevano kad njen odgovor legne, jer taj odgovor ne
        // zna za taj filter). Ovaj test proverava ISKLJUČIVO da `search`
        // (originalno prijavljeni bag) pouzdano preživljava — ne i sudbinu
        // `format`-a, koja je van opsega ove regresije.
        await wrapper.setProps({ filters: { ...EMPTY_FILTERS, category: 'romani', search: 'seobe' } });
        await nextTick();
        finishCb();

        expect(router.get).toHaveBeenCalledTimes(1);
        const [, params] = router.get.mock.calls[0];
        expect(params.search).toBe('seobe');
        expect(params.category).toBe('romani');
    });

    it('promena cene ne pravi redundantan drugi apply() kad se form sinhronizuje iz props-a', async () => {
        const wrapper = mountShop({});

        // Simulira spoljnu promenu (npr. "Poništi sve" ili back/forward) koja
        // menja price_min preko props-a, NE preko korisničkog kucanja.
        await wrapper.setProps({ filters: { ...EMPTY_FILTERS, price_min: '5' } });
        await nextTick();

        // syncingFromProps flag treba da spreči da se debounce watch (na
        // form.price_min) okine kao da je korisnik otkucao nešto - bez toga
        // bi setTimeout zakazao redundantan apply() 400ms kasnije.
        await new Promise((resolve) => setTimeout(resolve, 450));

        expect(router.get).not.toHaveBeenCalled();
    });
});

describe('Shop.vue — aktivni filteri kao chip-ovi (DEO B)', () => {
    it('prikazuje chip za svaki aktivan filter, uključujući search sa čitljivom labelom', () => {
        const wrapper = mountShop({ category: 'romani', search: 'seobe', price_min: '10' });
        const text = wrapper.text();

        expect(text).toContain('Romani'); // razrešeno iz options.categories, ne sirovi slug
        expect(text).toContain('Pretraga: seobe');
        expect(text).toContain('od 10.00 €');
    });

    it('ne prikazuje chip red kad nema aktivnih filtera', () => {
        const wrapper = mountShop({});
        expect(wrapper.text()).not.toContain('Poništi sve');
    });

    it('klik na × na search chip-u uklanja SAMO search, ostali filteri ostaju', async () => {
        const wrapper = mountShop({ category: 'romani', search: 'seobe' });

        const searchChipButton = wrapper.findAll('button').find((b) => b.attributes('aria-label')?.includes('Pretraga: seobe'));
        expect(searchChipButton).toBeTruthy();
        await searchChipButton.trigger('click');

        expect(router.get).toHaveBeenCalledTimes(1);
        const [, params] = router.get.mock.calls[0];
        expect(params).toEqual({ category: 'romani' }); // search uklonjen, category ostaje
    });

    it('klik na × na category chip-u uklanja SAMO category (form.category -> null), search ostaje', async () => {
        const wrapper = mountShop({ category: 'romani', search: 'seobe' });

        const categoryChipButton = wrapper.findAll('button').find((b) => b.attributes('aria-label')?.includes('Romani'));
        await categoryChipButton.trigger('click');

        expect(router.get).toHaveBeenCalledTimes(1);
        const [, params] = router.get.mock.calls[0];
        expect(params).toEqual({ search: 'seobe' }); // category uklonjen, search (iz props.filters) ostaje
    });

    it('"Poništi sve" je link ka route(\'shop\') bez ijednog parametra', () => {
        const wrapper = mountShop({ category: 'romani', search: 'seobe' });

        const resetLink = wrapper.findAll('a').find((a) => a.text() === 'Poništi sve');
        expect(resetLink).toBeTruthy();
        expect(resetLink.attributes('href')).toBe('shop');
    });
});
