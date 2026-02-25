<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';


const props = defineProps({
    order: Object,
});


</script>

<template>
    <Head title="Order Confirmed – Cash on Delivery" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-8 text-center">
                        <div class="mb-8">
                            <svg class="w-24 h-24 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>

                        <h1 class="text-4xl font-bold text-green-700 mb-4">Thank You!</h1>
                        <p class="text-xl text-gray-700 mb-8">
                            Your order has been successfully placed with <strong>Cash on Delivery</strong>.
                        </p>

                        <div class="bg-gray-50 p-6 rounded-lg mb-8 max-w-lg mx-auto">
                            <h2 class="text-2xl font-semibold mb-4">Order #{{ props.order.id }}</h2>
                            <p class="text-lg mb-2">
                                Total amount: <strong>{{ Number(props.order.total_price).toFixed(2) }} €</strong>
                            </p>
                            <p class="text-gray-600 mb-4">
                                You will pay this amount in cash to the courier when your package arrives.
                            </p>
                            <p class="text-sm text-gray-500">
                                We will send you a confirmation email with tracking details soon.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <p class="text-gray-600">
                                Order details and delivery information have been sent to:
                                <strong>{{ props.order.customer_email }}</strong>
                            </p>

                            <a 
                                :href="route('shop')" 
                                class="inline-block bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition text-lg font-medium"
                            >
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>