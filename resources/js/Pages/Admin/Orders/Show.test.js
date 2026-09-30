import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from './Show.vue';

// Show.vue zove route()/router.patch iz <script setup> JS koda (van template-a)
// - ide preko pravog window.route-a (Ziggy), ne Inertia-inog Vue plugin-a, pa
// testovi moraju globalThis.route (isti obrazac kao Checkout.test.js/Shop.test.js).
vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div style="display:none" />' },
    router: { patch: vi.fn(), delete: vi.fn() },
}));

import { router } from '@inertiajs/vue3';

function baseOrder(overrides = {}) {
    return {
        id: 1,
        first_name: 'Pera',
        last_name: 'Perić',
        customer_email: 'pera@example.com',
        phone: '0601234567',
        address: 'Stara adresa 1',
        city: 'Beograd',
        postal_code: '11000',
        notes: null,
        total_price: 19.99,
        status: 'pending',
        payment_method: 'cod',
        user: null,
        items: [],
        shipping_recipient_name: null,
        shipping_phone: null,
        shipping_line1: null,
        shipping_line2: null,
        shipping_city: null,
        shipping_postal_code: null,
        shipping_country: null,
        ...overrides,
    };
}

function mountShow(order) {
    return mount(Show, {
        props: { order },
        global: {
            // route() u <template>-u (DeleteConfirmation :delete-url) ide preko
            // global.mocks, ne globalThis.route (koji pokriva samo <script
            // setup> JS pozive, npr. updateStatus) — vidi CLAUDE.md gotcha.
            mocks: { route: (name) => name },
            stubs: {
                AuthenticatedLayout: { template: '<div><slot /></div>' },
            },
        },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    globalThis.route = (name) => name;
    globalThis.confirm = vi.fn(() => true);
});

describe('Show.vue — status akcije zavise od trenutnog statusa', () => {
    it('pending prikazuje dugmad za processing/paid/cancelled', () => {
        const wrapper = mountShow(baseOrder({ status: 'pending' }));
        const text = wrapper.text();

        expect(text).toContain('U obradi');
        expect(text).toContain('Plaćeno');
        expect(text).toContain('Otkazano');
        expect(text).not.toContain('Poslato');
    });

    it('processing prikazuje dugme za shipped (ne samo paid/cancelled)', () => {
        const wrapper = mountShow(baseOrder({ status: 'processing' }));
        const buttons = wrapper.findAll('button').map((b) => b.text());

        expect(buttons.some((t) => t.includes('Plaćeno'))).toBe(true);
        expect(buttons.some((t) => t.includes('Poslato'))).toBe(true);
        expect(buttons.some((t) => t.includes('Otkazano'))).toBe(true);
    });

    it('paid prikazuje dugme za shipped, ne za processing (nema nazad)', () => {
        const wrapper = mountShow(baseOrder({ status: 'paid' }));
        const buttons = wrapper.findAll('button').map((b) => b.text());

        expect(buttons.some((t) => t.includes('Poslato'))).toBe(true);
        expect(buttons.some((t) => t.includes('U obradi'))).toBe(false);
    });

    it('shipped prikazuje SAMO dugme za delivered', () => {
        const wrapper = mountShow(baseOrder({ status: 'shipped' }));
        const buttons = wrapper.findAll('button').filter((b) => b.text().startsWith('Označi kao'));

        expect(buttons).toHaveLength(1);
        expect(buttons[0].text()).toContain('Dostavljeno');
    });

    it('delivered je terminalno — nema nijedno dugme za promenu statusa', () => {
        const wrapper = mountShow(baseOrder({ status: 'delivered' }));
        const buttons = wrapper.findAll('button').filter((b) => b.text().startsWith('Označi kao'));

        expect(buttons).toHaveLength(0);
    });

    it('cancelled i failed su takođe terminalni', () => {
        for (const status of ['cancelled', 'failed', 'completed']) {
            const wrapper = mountShow(baseOrder({ status }));
            const buttons = wrapper.findAll('button').filter((b) => b.text().startsWith('Označi kao'));
            expect(buttons).toHaveLength(0);
        }
    });

    it('klik na status dugme zove router.patch sa novim statusom', async () => {
        const wrapper = mountShow(baseOrder({ status: 'pending' }));
        const cancelButton = wrapper.findAll('button').find((b) => b.text().includes('Otkazano'));

        await cancelButton.trigger('click');

        expect(router.patch).toHaveBeenCalledWith(
            'admin.orders.update',
            { status: 'cancelled' },
            expect.objectContaining({ preserveState: true, preserveScroll: true })
        );
    });
});

describe('Show.vue — adresa isporuke', () => {
    it('koristi shipping_* snapshot kad postoji, ne stari address string', () => {
        const wrapper = mountShow(baseOrder({
            status: 'pending',
            address: 'Stara adresa 1',
            shipping_recipient_name: 'Marko Marković',
            shipping_line1: 'Nova adresa 5',
            shipping_line2: 'Stan 3',
            shipping_city: 'Novi Sad',
            shipping_postal_code: '21000',
            shipping_country: 'Srbija',
            shipping_phone: '0659876543',
        }));

        const text = wrapper.text();
        expect(text).toContain('Marko Marković');
        expect(text).toContain('Nova adresa 5');
        expect(text).toContain('Stan 3');
        expect(text).toContain('Novi Sad');
        expect(text).not.toContain('Stara adresa 1');
    });

    it('pada nazad na stara polja kad shipping_* snapshot ne postoji (istorijska porudžbina)', () => {
        const wrapper = mountShow(baseOrder({
            status: 'pending',
            address: 'Stara adresa 1',
            city: 'Beograd',
        }));

        const text = wrapper.text();
        expect(text).toContain('Stara adresa 1');
        expect(text).toContain('Beograd');
    });
});
