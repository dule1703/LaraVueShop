import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import BookCard from './BookCard.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const BASE_BOOK = {
    product_id: 43,
    slug: 'prokleta-avlija',
    title: 'Prokleta avlija',
    image: null,
    price: 12.5,
    stock: 5,
    available: true,
    format: 'paperback',
    authors: ['Ivo Andrić'],
};

function mountCard(bookOverrides = {}) {
    return mount(BookCard, {
        props: { book: { ...BASE_BOOK, ...bookOverrides } },
        global: {
            // U pravoj aplikaciji `route()` dolazi iz Ziggy-ja registrovanog kao
            // app.config.globalProperties preko app.use(ZiggyVue) (vidi app.js).
            // Ovde se ne mount-uje ceo app, pa `route` mora ići kroz `global.mocks`
            // (vue-test-utils) - template kompajlira bare `route(...)` poziv u
            // `_ctx.route(...)`, obično `globalThis.route` (kako to radi
            // auth.test.js) tu ne pomaže jer se ne mount-uje pravi <template>.
            mocks: { route: (name) => name },
            stubs: { Link: true },
        },
    });
}

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('BookCard', () => {
    it('"U korpu" dugme je omogućeno kad je knjiga dostupna i ima ograničenu zalihu', () => {
        const wrapper = mountCard({ available: true, stock: 5 });
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeUndefined();
    });

    it('"U korpu" dugme je onemogućeno kad knjiga nije dostupna (available === false)', () => {
        const wrapper = mountCard({ available: false, stock: 0 });
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeDefined();
    });

    // stock === null = e-knjiga; checkout još ne podržava neograničene zalihe (Faza 5),
    // pa dugme na kartici ostaje onemogućeno i pored available === true.
    it('"U korpu" dugme je onemogućeno kad je stock === null (e-knjiga)', () => {
        const wrapper = mountCard({ available: true, stock: null });
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('klik na omogućeno dugme zove cart.addItem sa product_id i ne baca grešku', async () => {
        const wrapper = mountCard({ available: true, stock: 5 });

        await wrapper.find('button').trigger('click');

        expect(wrapper.find('button').text()).toBe('Dodato ✓');
        expect(axios.post).not.toHaveBeenCalled(); // gost - syncWithBackend se tiho preskače
    });
});
