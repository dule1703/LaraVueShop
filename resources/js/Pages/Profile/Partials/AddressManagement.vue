<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    addresses: {
        type: Array,
        default: () => [],
    },
});

const showModal = ref(false);
// null = "Dodaj novu adresu" mod; objekat adrese = "Izmeni" mod.
const editingAddress = ref(null);

const form = useForm({
    recipient_name: '',
    phone: '',
    line1: '',
    line2: '',
    city: '',
    postal_code: '',
    country: 'Srbija',
    is_default: false,
});

// Odvojene, prazne useForm instance za akcije bez tela zahteva - svaka
// akcija (dodaj/izmeni, obriši, postavi podrazumevanu) ide kroz sopstveni
// useForm() submit, isti obrazac kao DeleteUserForm.vue.
const deleteForm = useForm({});
const defaultForm = useForm({});

function openAddModal() {
    editingAddress.value = null;
    form.reset();
    form.clearErrors();
    form.country = 'Srbija';
    showModal.value = true;
}

function openEditModal(address) {
    editingAddress.value = address;
    form.clearErrors();
    form.recipient_name = address.recipient_name;
    form.phone = address.phone;
    form.line1 = address.line1;
    form.line2 = address.line2 ?? '';
    form.city = address.city;
    form.postal_code = address.postal_code;
    form.country = address.country ?? 'Srbija';
    form.is_default = address.is_default;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingAddress.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingAddress.value) {
        form.patch(route('addresses.update', editingAddress.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('addresses.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function deleteAddress(address) {
    if (!window.confirm(`Da li ste sigurni da želite da obrišete adresu "${address.recipient_name}"?`)) {
        return;
    }

    deleteForm.delete(route('addresses.destroy', address.id), { preserveScroll: true });
}

function setDefault(address) {
    defaultForm.patch(route('addresses.setDefault', address.id), { preserveScroll: true });
}
</script>

<template>
    <section class="space-y-6">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">
                    Sačuvane adrese
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Upravljajte adresama za dostavu koje možete izabrati prilikom plaćanja.
                </p>
            </div>

            <PrimaryButton @click="openAddModal">Dodaj novu adresu</PrimaryButton>
        </header>

        <div v-if="addresses.length === 0" class="rounded-lg border border-dashed border-black/10 p-6 text-center text-sm text-brand-text-secondary">
            Još uvek nemate sačuvanih adresa.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
                v-for="address in addresses"
                :key="address.id"
                class="rounded-xl border border-black/10 bg-brand-card p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-brand-text-primary">{{ address.recipient_name }}</p>
                        <p class="text-sm text-brand-text-secondary">{{ address.phone }}</p>
                    </div>
                    <span
                        v-if="address.is_default"
                        class="shrink-0 rounded-full bg-brand-accent px-2 py-0.5 text-xs font-semibold text-white"
                    >
                        Podrazumevana
                    </span>
                </div>

                <p class="mt-2 text-sm text-brand-text-primary">
                    {{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span><br />
                    {{ address.postal_code }} {{ address.city }}<span v-if="address.country">, {{ address.country }}</span>
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <button type="button" class="font-medium text-brand-accent hover:text-brand-accent-hover" @click="openEditModal(address)">
                        Izmeni
                    </button>
                    <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="deleteAddress(address)">
                        Obriši
                    </button>
                    <button
                        v-if="!address.is_default"
                        type="button"
                        class="font-medium text-brand-accent hover:text-brand-accent-hover"
                        @click="setDefault(address)"
                    >
                        Postavi kao podrazumevanu
                    </button>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingAddress ? 'Izmeni adresu' : 'Dodaj novu adresu' }}
                </h2>

                <form class="mt-6 space-y-4" @submit.prevent="submitForm">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="am-recipient-name" value="Ime i prezime primaoca" />
                            <TextInput id="am-recipient-name" v-model="form.recipient_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.recipient_name" />
                        </div>

                        <div>
                            <InputLabel for="am-phone" value="Telefon" />
                            <TextInput id="am-phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.phone" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="am-line1" value="Adresa (ulica i broj)" />
                            <TextInput id="am-line1" v-model="form.line1" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.line1" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="am-line2" value="Sprat/stan (opciono)" />
                            <TextInput id="am-line2" v-model="form.line2" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.line2" />
                        </div>

                        <div>
                            <InputLabel for="am-city" value="Grad" />
                            <TextInput id="am-city" v-model="form.city" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.city" />
                        </div>

                        <div>
                            <InputLabel for="am-postal-code" value="Poštanski broj" />
                            <TextInput id="am-postal-code" v-model="form.postal_code" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.postal_code" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="am-country" value="Država" />
                            <TextInput id="am-country" v-model="form.country" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.country" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="form-checkbox h-4 w-4 rounded border-black/20 text-brand-accent focus:ring-brand-accent"
                        />
                        Postavi kao podrazumevanu adresu
                    </label>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="closeModal">Otkaži</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="form.processing">Sačuvaj</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </section>
</template>
