<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        default: () => ({ items: [] }), // default da izbegne undefined
    },
});

const loading = ref(false);
const errorMessage = ref(null);

const payWithPayPal = () => {
    loading.value = true;
    errorMessage.value = null;
    window.location.href = route('paypal.createPayment', props.order.id);
};
</script>

<template>
    <Head title="Checkout" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h1 class="text-3xl font-bold mb-8">Checkout</h1>

                        <!-- Pregled porudžbine -->
                        <div class="mb-10">
                            <h2 class="text-2xl font-semibold mb-4">Vaša porudžbina</h2>
                            <div class="border rounded-lg p-6 bg-gray-50">
                                <div class="flex justify-between mb-4">
                                    <span class="text-lg">Ukupno:</span>
                                    <span class="text-xl font-bold">
                                        {{ props.order.total_price ?? 0 }} €
                                    </span>
                                </div>
                                <p class="text-gray-600">
                                    Broj stavki: {{ props.order.items?.length ?? 0 }}
                                </p>
                            </div>
                        </div>

                        <!-- PayPal dugme -->
                        <div class="mt-10">
                            <form :action="route('paypal.createPayment', props.order.id)" method="get">
    <button
        type="submit"
        :disabled="loading"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg text-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
    >
        <span v-if="loading" class="animate-pulse">Preusmeravanje...</span>
        <span v-else>Plati sa PayPal</span>
    </button>
</form>

                            <p class="text-center text-sm text-gray-500 mt-4">
                                Secure payment via PayPal. You will be redirected to PayPal.
                            </p>

                            <p v-if="errorMessage" class="text-center text-red-600 mt-4">
                                {{ errorMessage }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>