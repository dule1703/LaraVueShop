import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import Checkout from './Checkout.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

// Checkout.vue čita `page.props.auth?.user` i `page.props.flash` direktno iz
// usePage() (ne preko global.mocks - vidi HeaderSearch.test.js za isti
// obrazac) - `pageState` je mutable preko testova, mora biti `vi.hoisted` da
// bude dostupan unutar vi.mock() factory-ja (hoisting).
const { pageState } = vi.hoisted(() => ({
    pageState: { props: { auth: { user: null }, flash: {} } },
}));

function createMockForm(initial) {
    const state = reactive({
        ...initial,
        errors: {},
        processing: false,
        post: vi.fn(),
        reset: vi.fn(() => {
            Object.keys(initial).forEach((key) => {
                state[key] = initial[key];
            });
        }),
        clearErrors: vi.fn(() => {
            state.errors = {};
        }),
    });
    return state;
}

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div style="display:none" />' },
    usePage: () => pageState,
    useForm: vi.fn((initial) => createMockForm(initial)),
}));

const DEFAULT_ADDRESS = {
    id: 10,
    recipient_name: 'Petar Petrović',
    phone: '0601234567',
    line1: 'Kneza Miloša 10',
    line2: null,
    city: 'Beograd',
    postal_code: '11000',
    country: 'Srbija',
    is_default: true,
};

const SECOND_ADDRESS = {
    id: 20,
    recipient_name: 'Ana Anić',
    phone: '0659876543',
    line1: 'Bulevar Oslobođenja 5',
    line2: null,
    city: 'Novi Sad',
    postal_code: '21000',
    country: 'Srbija',
    is_default: false,
};

function mountCheckout(addresses = []) {
    return mount(Checkout, {
        props: { addresses },
        global: {
            stubs: {
                AuthenticatedLayout: { template: '<div><slot /></div>' },
            },
        },
    });
}

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    globalThis.route = (name) => name;
    pageState.props = { auth: { user: null }, flash: {} };
});

describe('Checkout.vue — izbor adrese', () => {
    it('ulogovan korisnik sa sačuvanim adresama: address_id je unapred podešen na podrazumevanu (prvu), inline polja su sakrivena', async () => {
        pageState.props.auth.user = { email: 'petar@example.com' };
        const wrapper = mountCheckout([DEFAULT_ADDRESS, SECOND_ADDRESS]);
        await wrapper.vm.$nextTick();

        const select = wrapper.find('#address_id');
        expect(select.exists()).toBe(true);
        expect(select.element.value).toBe(String(DEFAULT_ADDRESS.id));

        // Inline polja se ne prikazuju dok je izabrana sačuvana adresa.
        expect(wrapper.find('#recipient_name').exists()).toBe(false);
        expect(wrapper.find('#line1').exists()).toBe(false);
    });

    it('izbor "+ Nova adresa" postavlja address_id na null i otkriva inline polja + checkbox "sačuvaj kao podrazumevanu"', async () => {
        pageState.props.auth.user = { email: 'petar@example.com' };
        const wrapper = mountCheckout([DEFAULT_ADDRESS, SECOND_ADDRESS]);
        await wrapper.vm.$nextTick();

        // Opcija "+ Nova adresa" ima :value="null" - bez value atributa u DOM-u,
        // pa native <option> value pada na tekst opcije ("+ Nova adresa").
        // Vue-ov vModelSelect ipak čita internu `_value` (pravi null) pri change
        // event-u, ne stringovanu DOM vrednost.
        await wrapper.find('#address_id').setValue('+ Nova adresa');

        expect(wrapper.find('#recipient_name').exists()).toBe(true);
        expect(wrapper.find('#line1').exists()).toBe(true);
        expect(wrapper.find('#city').exists()).toBe(true);

        // Checkbox "Sačuvaj kao podrazumevanu adresu" - samo za ulogovanog
        // korisnika koji unosi novu adresu.
        const checkbox = wrapper.findAll('input[type="checkbox"]');
        expect(checkbox).toHaveLength(1);
    });

    it('gost (bez page.props.auth.user) nikad ne vidi select ni checkbox, samo inline polja; address_id ostaje null', async () => {
        pageState.props.auth.user = null;
        // Gost uvek dobija prazan niz adresa sa servera (garantovano
        // ugovorom rute) - prosleđujemo prazan niz kao i backend.
        const wrapper = mountCheckout([]);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('#address_id').exists()).toBe(false);
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);

        expect(wrapper.find('#recipient_name').exists()).toBe(true);
        expect(wrapper.find('#line1').exists()).toBe(true);
        expect(wrapper.find('#city').exists()).toBe(true);
    });

    it('ulogovan korisnik BEZ sačuvanih adresa: nema select-a, inline polja + checkbox su odmah vidljivi', async () => {
        pageState.props.auth.user = { email: 'nov@example.com' };
        const wrapper = mountCheckout([]);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('#address_id').exists()).toBe(false);
        expect(wrapper.find('#recipient_name').exists()).toBe(true);
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(1);
    });
});
