import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PaymentFailed from './PaymentFailed.vue';

// PaymentFailed.vue čita `page.props.flash?.error` direktno iz usePage()
// (isti obrazac kao Checkout.test.js) - `pageState` mora biti `vi.hoisted`
// da bude dostupan unutar vi.mock() factory-ja.
const { pageState } = vi.hoisted(() => ({
    pageState: { props: { flash: {} } },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<div style="display:none" />' },
    usePage: () => pageState,
}));

const ORDER = {
    id: 21,
    total_price: 19.99,
    status: 'failed',
};

function mountPage() {
    return mount(PaymentFailed, {
        props: { order: ORDER },
        global: {
            stubs: {
                AuthenticatedLayout: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('PaymentFailed', () => {
    it('prikazuje konkretan razlog iz flash.error kad postoji', () => {
        pageState.props.flash = { error: 'INSTRUMENT_DECLINED - kartica odbijena.' };

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('INSTRUMENT_DECLINED - kartica odbijena.');
    });

    it('ne prikazuje "Razlog" blok kad flash.error nije postavljen', () => {
        pageState.props.flash = {};

        const wrapper = mountPage();

        expect(wrapper.text()).not.toContain('Razlog:');
    });

    it('ne tvrdi pogrešnu PERMISSION_DENIED/sandbox dijagnozu', () => {
        pageState.props.flash = {};

        const wrapper = mountPage();

        expect(wrapper.text()).not.toContain('PERMISSION_DENIED');
        expect(wrapper.text()).not.toContain('Sandbox permission');
    });
});
