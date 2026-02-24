<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const cart = useCartStore();

// Kreiramo formu pomoću useForm
const form = useForm({
    first_name: '',
    last_name: '',
    address: '',
    email: '',
    city: '',
    postal_code: '',
    phone: '',
    notes: '',
    items: [],          
    total_price: 0,
});

// Popuni formu ako je korisnik ulogovan
onMounted(() => {
    if (usePage().props.auth?.user) {
        form.first_name = usePage().props.auth.user.first_name || '';
        form.last_name  = usePage().props.auth.user.last_name  || '';
        form.email = usePage().props.auth.user.email || '';
    }

    // Sinhronizuj korpu
    if (usePage().props.auth?.user) {
        cart.loadFromBackend();
    } else {
        cart.loadFromLocalStorage();
    }

    // Sinhronizuj korpu sa formom (za slanje na backend)
    form.items = cart.items;
    form.total_price = cart.totalAmount;
});

// Ukupna cena (za prikaz)
const totalPrice = computed(() => cart.totalAmount);

// Validacija na frontendu (opciono, backend će ionako proveriti)
const isFormValid = computed(() => {
    return form.first_name.trim() &&
           form.last_name.trim() &&
           form.address.trim() &&
           form.email.trim() && form.email.includes('@') &&
           form.city.trim() &&
           form.postal_code.trim() &&
           form.phone.trim() &&
           cart.items.length > 0;
});

const submit = () => {
    // Sinhronizuj korpu neposredno pre slanja (za slučaj da se promenila)
    form.items = cart.items;
    form.total_price = cart.totalAmount;

    form.post(route('orders.store'), {
        onStart: () => {
            form.processing = true; // automatski, ali možeš koristiti i ref
        },
        onFinish: () => {
            form.processing = false;
        },
        onSuccess: (page) => {
            const orderId = page.props.order?.id;
            if (orderId) {
                window.location.href = route('paypal.createPayment', orderId);
            }
        },
        onError: (errors) => {
            console.log('Greške sa backend-a:', errors);            
        },
    });
};
</script>

<template>
    <Head title="Payment" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 lg:p-10">
                        <h1 class="text-3xl font-bold mb-10">Final order</h1>

                        <!-- Pregled korpe -->
                        <div class="mb-12">
                            <h2 class="text-2xl font-semibold mb-6">Your cart</h2>
                            <div v-if="cart.isEmpty" class="text-center py-8 text-gray-500">
                                Cart is empty
                            </div>
                            <div v-else class="border rounded-lg overflow-hidden">
                                <div v-for="item in cart.items" :key="item.id" class="flex items-center justify-between p-4 border-b last:border-b-0">
                                    <div class="flex items-center gap-4">
                                        <img v-if="item.image" :src="item.image" class="w-16 h-16 object-cover rounded" />
                                        <div>
                                            <h3 class="font-medium">{{ item.name }}</h3>
                                            <p class="text-sm text-gray-600">{{ Number(item.price).toFixed(2) }} € × {{ item.quantity }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right font-medium">
                                        {{ (item.price * item.quantity).toFixed(2) }} €
                                    </div>
                                </div>
                                <div class="p-6 bg-gray-50 flex justify-between text-xl font-bold">
                                    <span>Total:</span>
                                    <span class="text-blue-700">{{ totalPrice.toFixed(2) }} €</span>
                                </div>
                            </div>
                        </div>

                        <!-- Forma za adresu -->
                        <div class="mb-12">
                            <h2 class="text-2xl font-semibold mb-6">Address for Delivery</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium mb-1">First name *</label>
                                    <input v-model="form.first_name" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.first_name" class="text-red-600 text-sm mt-1">{{ form.errors.first_name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Last name *</label>
                                    <input v-model="form.last_name" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.last_name" class="text-red-600 text-sm mt-1">{{ form.errors.last_name }}</div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Address *</label>
                                    <input v-model="form.address" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.address" class="text-red-600 text-sm mt-1">{{ form.errors.address }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Email *</label>
                                    <input v-model="form.email" type="email" class="w-full border rounded px-4 py-2" required />
                                    <div v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">City *</label>
                                    <input v-model="form.city" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.city" class="text-red-600 text-sm mt-1">{{ form.errors.city }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Zip code *</label>
                                    <input v-model="form.postal_code" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.postal_code" class="text-red-600 text-sm mt-1">{{ form.errors.postal_code }}</div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Phone *</label>
                                    <input v-model="form.phone" type="tel" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.phone" class="text-red-600 text-sm mt-1">{{ form.errors.phone }}</div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Note (optional)</label>
                                    <textarea v-model="form.notes" rows="3" class="w-full border rounded px-4 py-2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Dugme za plaćanje -->
                        <div class="text-center">
                            <button
                                @click="submit"
                                :disabled="form.processing || cart.isEmpty || !form.isDirty"
                                class="inline-flex items-center px-10 py-5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xl rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                            >
                                <span v-if="form.processing" class="animate-pulse">Processing...</span>
                                <span v-else>Pay with PayPal</span>
                            </button>

                            <p class="mt-4 text-sm text-gray-500">                                
                                Secure payment over PayPal. We don't store card information.
                            </p>

                            <div v-if="form.errors.message || form.errors.items" class="mt-4 text-red-600 font-medium">
                                {{ form.errors.message || form.errors.items }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>