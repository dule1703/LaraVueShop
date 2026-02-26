<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const cart = useCartStore();

// Form setup
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    address: '',
    city: '',
    postal_code: '',
    phone: '',
    notes: '',
    payment_method: 'cod',  
    items: [],
    total_price: 0,
});

// Fill form if user is logged in
onMounted(() => {
    if (page.props.auth?.user) {
        form.first_name = page.props.auth.user.first_name || '';
        form.last_name  = page.props.auth.user.last_name  || '';
        form.email = page.props.auth.user.email || '';
    }

    // Sinhronizuj korpu
    if (page.props.auth?.user) {
        cart.loadFromBackend();
    } else {
        cart.loadFromLocalStorage();
    }

    // Sinhronizuj korpu sa formom
    form.items = cart.items;
    form.total_price = cart.totalAmount;
});

// Computed total price for display
const totalPrice = computed(() => cart.totalAmount);

// Basic frontend validation
const isFormValid = computed(() => {
    return form.first_name.trim() &&
           form.last_name.trim() &&
           form.email.trim() && form.email.includes('@') &&
           form.address.trim() &&
           form.city.trim() &&
           form.postal_code.trim() &&
           form.phone.trim() &&
           cart.items.length > 0;
});

const submit = () => {
    // Sinhronizuj korpu pre slanja
    form.items = cart.items;
    form.total_price = cart.totalAmount;

    console.log('📦 Submitting order:', {
        payment_method: form.payment_method,
        items_count: form.items.length,
        total: form.total_price
    });

    // ✅ Jednostavno submit - OrderController će raditi redirect
    form.post(route('orders.store'), {
        onStart: () => {
            console.log('🚀 Order submission started');
        },
        onSuccess: () => {
            console.log('✅ Order created - backend redirects now');
            // Očisti korpu nakon uspešne porudžbine
            cart.clearCart();
            // Backend će automatski redirect-ovati na PayPal ili COD success
        },
        onError: (errors) => {
            console.error('❌ Order submission errors:', errors);
        },
    });
};
</script>

<template>
    <Head title="Checkout" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 lg:p-10">
                        <h1 class="text-3xl font-bold mb-10">Checkout</h1>

                        <!-- Cart Summary -->
                        <div class="mb-12">
                            <h2 class="text-2xl font-semibold mb-6">Your Cart</h2>
                            <div v-if="cart.isEmpty" class="text-center py-8 text-gray-500">
                                Your cart is empty
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

                        <!-- Shipping Address -->
                        <div class="mb-12">
                            <h2 class="text-2xl font-semibold mb-6">Shipping Address</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium mb-1">First Name *</label>
                                    <input v-model="form.first_name" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.first_name" class="text-red-600 text-sm mt-1">{{ form.errors.first_name }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Last Name *</label>
                                    <input v-model="form.last_name" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.last_name" class="text-red-600 text-sm mt-1">{{ form.errors.last_name }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Email *</label>
                                    <input v-model="form.email" type="email" class="w-full border rounded px-4 py-2" required />
                                    <div v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Address *</label>
                                    <input v-model="form.address" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.address" class="text-red-600 text-sm mt-1">{{ form.errors.address }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">City *</label>
                                    <input v-model="form.city" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.city" class="text-red-600 text-sm mt-1">{{ form.errors.city }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Postal Code *</label>
                                    <input v-model="form.postal_code" type="text" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.postal_code" class="text-red-600 text-sm mt-1">{{ form.errors.postal_code }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Phone *</label>
                                    <input v-model="form.phone" type="tel" class="w-full border rounded px-4 py-2" />
                                    <div v-if="form.errors.phone" class="text-red-600 text-sm mt-1">{{ form.errors.phone }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Notes (optional)</label>
                                    <textarea v-model="form.notes" rows="3" class="w-full border rounded px-4 py-2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
<!-- Payment Method -->
<div class="mb-12">
    <h2 class="text-2xl font-semibold mb-6">Payment Method</h2>
    <div class="space-y-6 bg-gray-50 p-6 rounded-lg border">
        <label class="flex items-start cursor-pointer">
            <input
                type="radio"
                v-model="form.payment_method"
                value="paypal"
                class="mt-1 mr-3 h-5 w-5 text-blue-600 border-gray-300 focus:ring-blue-500"
            />
            <div>
                <div class="font-medium text-lg">PayPal / Credit Card</div>
                <p class="text-sm text-gray-600">Pay securely online now. You will be redirected to PayPal.</p>
            </div>
        </label>

        <!-- TEST DATA – visible ONLY when PayPal is selected -->
        <div v-if="form.payment_method === 'paypal'" class="mt-4 p-4 bg-yellow-50 border border-yellow-400 rounded-lg text-sm">
            <p class="font-semibold text-yellow-800 mb-2">Test PayPal credentials (sandbox):</p>
            <ul class="list-disc pl-5 space-y-1 text-gray-700">
                <li><strong>Email:</strong> sb-ybtyg48467509@personal.example.com</li>
                <li><strong>Password:</strong> T-9kqa1B</li>
                <li><strong>Visa card:</strong> 4111111111111111</li>
                <li><strong>MasterCard:</strong> 5148652529369811</li>
            </ul>
        </div>

        <label class="flex items-start cursor-pointer">
            <input
                type="radio"
                v-model="form.payment_method"
                value="cod"
                class="mt-1 mr-3 h-5 w-5 text-blue-600 border-gray-300 focus:ring-blue-500"
            />
            <div>
                <div class="font-medium text-lg">Cash on Delivery</div>
                <p class="text-sm text-gray-600">Pay in cash to the courier upon delivery.</p>
            </div>
        </label>
    </div>

    <div v-if="form.errors.payment_method" class="text-red-600 text-sm mt-2">
        {{ form.errors.payment_method }}
    </div>
</div>

                        <!-- Submit Button -->
                        <div class="text-center">
                            <button
                                @click="submit"
                                :disabled="form.processing || cart.isEmpty || !isFormValid"
                                class="inline-flex items-center px-10 py-5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xl rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                            >
                                <span v-if="form.processing" class="animate-pulse">Processing...</span>
                                <span v-else>
                                    {{ form.payment_method === 'cod' 
                                        ? 'Complete Order (Cash on Delivery)' 
                                        : 'Pay with PayPal / Card' }}
                                </span>
                            </button>

                            <p class="mt-4 text-sm text-gray-500">
                                Secure payment. We never store your card details.
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