import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AddressManagement from './AddressManagement.vue';

// AddressManagement.vue poziva useForm() TRI puta (form za dodaj/izmeni,
// deleteForm, defaultForm) - nijedan postojeći test fajl u projektu do sad
// nije mock-ovao useForm, pa ga ovde pravimo sami: minimalan objekat koji
// se ponaša dovoljno kao pravi Inertia form (reset/clearErrors/post/patch/
// delete su vi.fn() spy-ovi cija se ciljna ruta i redosled poziva mogu
// proveriti). `reactive()` je bitan - bez njega v-model u <template>-u
// (TextInput koristi defineModel) ne bi propagirao izmene nazad u ovaj
// objekat.
function createMockForm(initial) {
    const state = reactive({
        ...initial,
        errors: {},
        processing: false,
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
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
    useForm: vi.fn((initial) => createMockForm(initial)),
}));

const ADDRESSES = [
    {
        id: 5,
        recipient_name: 'Petar Petrović',
        phone: '0601234567',
        line1: 'Kneza Miloša 10',
        line2: null,
        city: 'Beograd',
        postal_code: '11000',
        country: 'Srbija',
        is_default: true,
    },
    {
        id: 7,
        recipient_name: 'Ana Anić',
        phone: '0659876543',
        line1: 'Bulevar Oslobođenja 5',
        line2: 'Stan 3',
        city: 'Novi Sad',
        postal_code: '21000',
        country: 'Srbija',
        is_default: false,
    },
];

// Modal.vue koristi native <dialog>.showModal(), koje jsdom ne implementira
// (baca "Not implemented" grešku) - stub-ujemo ga prostim v-if omotačem, isti
// pristup kao stubovanje AuthenticatedLayout/BookCard/Pagination u drugim
// test fajlovima (Shop.test.js).
const modalStub = {
    props: ['show'],
    emits: ['close'],
    template: '<div v-if="show"><slot /></div>',
};

function mountManagement(addresses = ADDRESSES) {
    return mount(AddressManagement, {
        props: { addresses },
        global: {
            stubs: { Modal: modalStub },
        },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    // route(name) u <script setup> JS kodu (deleteAddress/setDefault/submitForm)
    // mora ići preko globalThis.route (isti obrazac kao HeaderSearch.test.js/
    // Shop.test.js) - vraćamo "ime:param" da testovi mogu proveriti TAČAN id.
    globalThis.route = (name, param) => (param !== undefined ? `${name}:${param}` : name);
});

describe('AddressManagement', () => {
    it('prikazuje listu adresa, podrazumevana dobija bedž "Podrazumevana"', () => {
        const wrapper = mountManagement();

        expect(wrapper.text()).toContain('Petar Petrović');
        expect(wrapper.text()).toContain('Ana Anić');
        expect(wrapper.text()).toContain('Podrazumevana');

        // Samo JEDAN bedž - za default adresu, ne za obe.
        const badges = wrapper.findAll('span').filter((s) => s.text() === 'Podrazumevana');
        expect(badges).toHaveLength(1);
    });

    it('prazna lista adresa prikazuje poruku, dugme "Dodaj novu adresu" ostaje dostupno', () => {
        const wrapper = mountManagement([]);

        expect(wrapper.text()).toContain('Još uvek nemate sačuvanih adresa.');
        expect(wrapper.text()).toContain('Dodaj novu adresu');
    });

    it('"Dodaj novu adresu" otvara modal sa praznim poljima', async () => {
        const wrapper = mountManagement();

        await wrapper.findAll('button').find((b) => b.text() === 'Dodaj novu adresu').trigger('click');

        const recipientInput = wrapper.find('#am-recipient-name');
        expect(recipientInput.exists()).toBe(true);
        expect(recipientInput.element.value).toBe('');
    });

    it('klik na "Izmeni" otvara modal sa popunjenim poljima te adrese', async () => {
        const wrapper = mountManagement();

        const editButtons = wrapper.findAll('button').filter((b) => b.text() === 'Izmeni');
        await editButtons[1].trigger('click'); // Ana Anić (drugi red)

        expect(wrapper.find('#am-recipient-name').element.value).toBe('Ana Anić');
        expect(wrapper.find('#am-line1').element.value).toBe('Bulevar Oslobođenja 5');
        expect(wrapper.find('#am-city').element.value).toBe('Novi Sad');
    });

    it('slanje forme za dodavanje zove form.post ka addresses.store sa unetim vrednostima', async () => {
        const wrapper = mountManagement();

        await wrapper.findAll('button').find((b) => b.text() === 'Dodaj novu adresu').trigger('click');

        await wrapper.find('#am-recipient-name').setValue('Nova Osoba');
        await wrapper.find('#am-phone').setValue('0611112222');
        await wrapper.find('#am-line1').setValue('Ulica 1');
        await wrapper.find('#am-city').setValue('Niš');
        await wrapper.find('#am-postal-code').setValue('18000');

        await wrapper.find('form').trigger('submit');

        // Prvi useForm() poziv u komponenti je `form` (dodaj/izmeni).
        const formInstance = useForm.mock.results[0].value;

        expect(formInstance.post).toHaveBeenCalledTimes(1);
        expect(formInstance.post.mock.calls[0][0]).toBe('addresses.store');
        expect(formInstance.recipient_name).toBe('Nova Osoba');
        expect(formInstance.phone).toBe('0611112222');
        expect(formInstance.line1).toBe('Ulica 1');
        expect(formInstance.city).toBe('Niš');
        expect(formInstance.postal_code).toBe('18000');
    });

    it('slanje forme za izmenu zove form.patch ka addresses.update za tačan id', async () => {
        const wrapper = mountManagement();

        const editButtons = wrapper.findAll('button').filter((b) => b.text() === 'Izmeni');
        await editButtons[0].trigger('click'); // Petar Petrović, id 5

        await wrapper.find('form').trigger('submit');

        const formInstance = useForm.mock.results[0].value;
        expect(formInstance.patch).toHaveBeenCalledTimes(1);
        expect(formInstance.patch.mock.calls[0][0]).toBe('addresses.update:5');
    });

    it('"Obriši" (posle potvrde) zove delete ka addresses.destroy za tačan id', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = mountManagement();

        const deleteButtons = wrapper.findAll('button').filter((b) => b.text() === 'Obriši');
        await deleteButtons[1].trigger('click'); // Ana Anić, id 7

        // Drugi useForm() poziv u komponenti je deleteForm.
        const deleteFormInstance = useForm.mock.results[1].value;
        expect(deleteFormInstance.delete).toHaveBeenCalledTimes(1);
        expect(deleteFormInstance.delete.mock.calls[0][0]).toBe('addresses.destroy:7');
    });

    it('"Obriši" ne zove ništa ako korisnik otkaže potvrdu', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mountManagement();

        const deleteButtons = wrapper.findAll('button').filter((b) => b.text() === 'Obriši');
        await deleteButtons[0].trigger('click');

        const deleteFormInstance = useForm.mock.results[1].value;
        expect(deleteFormInstance.delete).not.toHaveBeenCalled();
    });

    it('"Postavi kao podrazumevanu" zove patch ka addresses.setDefault za tačan id, dugme se ne prikazuje za već podrazumevanu', async () => {
        const wrapper = mountManagement();

        // Petar Petrović (id 5) je već podrazumevan - dugme ne postoji za njega.
        const setDefaultButtons = wrapper.findAll('button').filter((b) => b.text() === 'Postavi kao podrazumevanu');
        expect(setDefaultButtons).toHaveLength(1);

        await setDefaultButtons[0].trigger('click');

        // Treći useForm() poziv u komponenti je defaultForm.
        const defaultFormInstance = useForm.mock.results[2].value;
        expect(defaultFormInstance.patch).toHaveBeenCalledTimes(1);
        expect(defaultFormInstance.patch.mock.calls[0][0]).toBe('addresses.setDefault:7');
    });
});
