<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { computed, onMounted } from 'vue';
import { formatPrice } from '@/lib/bookLabels';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    addresses: { type: Array, default: () => [] },
});

const page = usePage();
const cart = useCartStore();

const isAuthenticated = computed(() => !!page.props.auth?.user);

// Kad ulogovan korisnik ima sačuvane adrese, podrazumevana (prva u nizu -
// backend šalje default-first, vidi routes/web.php) je unapred izabrana.
// Za gosta `props.addresses` je uvek prazan niz (garantovano server-side),
// pa `address_id` ostaje `null` bez posebne provere ovde.
const form = useForm({
    email: '',
    notes: '',
    payment_method: 'cod',
    items: [],
    address_id: props.addresses.length > 0 ? props.addresses[0].id : null,
    save_address: false,
    shipping: {
        recipient_name: '',
        phone: '',
        line1: '',
        line2: '',
        city: '',
        postal_code: '',
        country: 'Srbija',
    },
});

// Select sa sačuvanim adresama se prikazuje samo ulogovanom korisniku koji IMA
// bar jednu sačuvanu adresu. Inline polja se prikazuju kad nema izabrane
// sačuvane adrese (address_id === null) - gost ih uvek vidi (nema select),
// ulogovan korisnik ih vidi kad izabere "+ Nova adresa" ili kad nema
// nijednu sačuvanu adresu.
const showAddressSelect = computed(() => isAuthenticated.value && props.addresses.length > 0);
const showInlineFields = computed(() => form.address_id === null);

function addressLabel(address) {
    return `${address.recipient_name} — ${address.line1}, ${address.city}`;
}

onMounted(async () => {
    if (page.props.auth?.user) {
        form.email = page.props.auth.user.email || '';
    }

    // Sinhronizuj korpu (loadFromBackend interno pada nazad na
    // loadFromLocalStorage() za gosta - vidi cart.js). Redosled load pa tek
    // onda hydrate MORA ostati ovakav - vidi CLAUDE.md "Korpa - refaktor..."
    // (dve prethodne trke koje su ovim redosledom rešene, ne diraj).
    await cart.loadFromBackend();
    await cart.hydrate();

    // Backend očekuje items.*.id (products.id) i items.*.quantity
    form.items = cart.items.map((item) => ({ id: item.product_id, quantity: item.quantity }));
});

const totalPrice = computed(() => cart.totalAmount);

// Osnovna frontend validacija (server je i dalje izvor istine preko 422-ki).
const isFormValid = computed(() => {
    if (!form.email.trim() || !form.email.includes('@')) return false;
    if (cart.items.length === 0) return false;
    if (form.address_id !== null) return true;

    return Boolean(
        form.shipping.recipient_name.trim() &&
        form.shipping.phone.trim() &&
        form.shipping.line1.trim() &&
        form.shipping.city.trim() &&
        form.shipping.postal_code.trim()
    );
});

const submit = () => {
    // Sinhronizuj korpu pre slanja (backend očekuje items.*.id, ne product_id)
    form.items = cart.items.map((item) => ({ id: item.product_id, quantity: item.quantity }));

    form.post(route('orders.store'), {
        onSuccess: () => {
            // Backend redirect-uje na PayPal ili COD success stranicu.
            cart.clearCart();
        },
    });
};
</script>

<template>
    <Head title="Plaćanje" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <h1 class="font-serif text-3xl font-semibold text-brand-text-primary mb-8">Plaćanje</h1>

                <div v-if="page.props.flash?.success" class="mb-6 rounded-xl border border-brand-accent bg-brand-card p-4 text-brand-text-primary">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ page.props.flash.error }}
                </div>

                <!-- Korpa -->
                <section class="mb-8 rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <h2 class="font-serif text-xl font-semibold text-brand-text-primary mb-4">Vaša korpa</h2>

                    <div v-if="cart.isEmpty" class="py-8 text-center text-brand-text-secondary">
                        Vaša korpa je prazna.
                    </div>
                    <div v-else class="divide-y divide-black/5">
                        <div
                            v-for="item in cart.items"
                            :key="item.product_id"
                            class="flex items-center gap-4 py-4"
                        >
                            <img
                                v-if="cart.productDetails[item.product_id]?.image"
                                :src="cart.productDetails[item.product_id].image"
                                class="h-16 w-16 shrink-0 rounded-lg object-cover"
                            />
                            <div v-else class="h-16 w-16 shrink-0 rounded-lg bg-brand-card"></div>

                            <div class="flex-1">
                                <h3 class="font-medium text-brand-text-primary">{{ cart.productDetails[item.product_id]?.name }}</h3>
                                <p class="text-sm text-brand-text-secondary">
                                    {{ formatPrice(cart.productDetails[item.product_id]?.price ?? 0) }} × {{ item.quantity }}
                                </p>
                            </div>

                            <div class="font-semibold text-brand-text-primary">
                                {{ formatPrice((cart.productDetails[item.product_id]?.price ?? 0) * item.quantity) }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 text-lg font-bold text-brand-text-primary">
                            <span>Ukupno:</span>
                            <span class="text-brand-accent">{{ formatPrice(totalPrice) }}</span>
                        </div>
                    </div>
                </section>

                <!-- Adresa za dostavu -->
                <section class="mb-8 rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <h2 class="font-serif text-xl font-semibold text-brand-text-primary mb-4">Adresa za dostavu</h2>

                    <div class="mb-4">
                        <label for="email" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Email *</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent"
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div v-if="showAddressSelect" class="mb-4">
                        <label for="address_id" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Adresa</label>
                        <div class="relative">
                            <select
                                id="address_id"
                                v-model="form.address_id"
                                class="block w-full cursor-pointer appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-10 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent"
                            >
                                <option v-for="address in addresses" :key="address.id" :value="address.id">
                                    {{ addressLabel(address) }}
                                </option>
                                <option :value="null">+ Nova adresa</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-secondary" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.address_id" />
                    </div>

                    <div v-if="showInlineFields" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="recipient_name" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Ime i prezime primaoca *</label>
                            <input id="recipient_name" v-model="form.shipping.recipient_name" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.recipient_name']" />
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Telefon *</label>
                            <input id="phone" v-model="form.shipping.phone" type="tel" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.phone']" />
                        </div>
                        <div class="md:col-span-2">
                            <label for="line1" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Adresa (ulica i broj) *</label>
                            <input id="line1" v-model="form.shipping.line1" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.line1']" />
                        </div>
                        <div class="md:col-span-2">
                            <label for="line2" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Sprat/stan (opciono)</label>
                            <input id="line2" v-model="form.shipping.line2" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.line2']" />
                        </div>
                        <div>
                            <label for="city" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Grad *</label>
                            <input id="city" v-model="form.shipping.city" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.city']" />
                        </div>
                        <div>
                            <label for="postal_code" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Poštanski broj *</label>
                            <input id="postal_code" v-model="form.shipping.postal_code" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.postal_code']" />
                        </div>
                        <div class="md:col-span-2">
                            <label for="country" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Država</label>
                            <input id="country" v-model="form.shipping.country" type="text" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            <InputError class="mt-1" :message="form.errors['shipping.country']" />
                        </div>

                        <label v-if="isAuthenticated" class="md:col-span-2 flex items-center gap-2 text-sm text-brand-text-primary">
                            <input v-model="form.save_address" type="checkbox" class="form-checkbox h-4 w-4 rounded border-black/20 text-brand-accent focus:ring-brand-accent" />
                            Sačuvaj kao podrazumevanu adresu
                        </label>
                    </div>

                    <div class="mt-4">
                        <label for="notes" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary mb-1">Napomena (opciono)</label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 px-3 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent"
                        ></textarea>
                    </div>
                </section>

                <!-- Način plaćanja -->
                <section class="mb-8 rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <h2 class="font-serif text-xl font-semibold text-brand-text-primary mb-4">Način plaćanja</h2>

                    <div class="space-y-3">
                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                            :class="form.payment_method === 'paypal' ? 'border-brand-accent bg-brand-card' : 'border-black/10'"
                        >
                            <input
                                type="radio"
                                v-model="form.payment_method"
                                value="paypal"
                                class="form-radio mt-1 h-5 w-5 border-black/20 text-brand-accent focus:ring-brand-accent"
                            />
                            <div>
                                <div class="font-medium text-brand-text-primary">PayPal / kartica</div>
                                <p class="text-sm text-brand-text-secondary">Bezbedno plaćanje onlajn. Bićete preusmereni na PayPal (sandbox test okruženje) radi plaćanja.</p>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                            :class="form.payment_method === 'cod' ? 'border-brand-accent bg-brand-card' : 'border-black/10'"
                        >
                            <input
                                type="radio"
                                v-model="form.payment_method"
                                value="cod"
                                class="form-radio mt-1 h-5 w-5 border-black/20 text-brand-accent focus:ring-brand-accent"
                            />
                            <div>
                                <div class="font-medium text-brand-text-primary">Pouzećem</div>
                                <p class="text-sm text-brand-text-secondary">Plaćate gotovinom kuriru prilikom preuzimanja pošiljke.</p>
                            </div>
                        </label>
                    </div>

                    <InputError class="mt-2" :message="form.errors.payment_method" />
                </section>

                <!-- Submit -->
                <div class="text-center">
                    <button
                        @click="submit"
                        :disabled="form.processing || cart.isEmpty || !isFormValid"
                        class="inline-flex items-center rounded-full bg-brand-accent px-10 py-4 text-lg font-semibold text-white shadow-lg transition hover:bg-brand-accent-hover disabled:cursor-not-allowed disabled:bg-brand-header-muted"
                    >
                        <span v-if="form.processing" class="animate-pulse">Obrada u toku...</span>
                        <span v-else>
                            {{ form.payment_method === 'cod'
                                ? 'Završi porudžbinu (pouzećem)'
                                : 'Plati PayPal-om / karticom' }}
                        </span>
                    </button>

                    <p class="mt-4 text-sm text-brand-text-secondary">
                        Bezbedno plaćanje. Nikada ne čuvamo podatke vaše kartice.
                    </p>

                    <div v-if="form.errors.message || form.errors.items" class="mt-4 font-medium text-red-600">
                        {{ form.errors.message || form.errors.items }}
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
