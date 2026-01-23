<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    order: Object,
});
</script>

<template>
    <Head title="Payment Successful" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-10 text-center">
                        <div class="text-green-600 text-8xl mb-8">✓</div>
                        <h1 class="text-4xl font-bold mb-6">Payment Successful!</h1>

                        <p class="text-xl mb-8">
                            Thank you for your order #{{ order.id }}.
                        </p>

                        <div class="text-lg mb-10">
                            <p>Total amount: <strong>{{ order.total_price }} €</strong></p>
                            <p class="mt-2">
                                Status: <strong class="text-green-600">{{ order.status }}</strong>
                            </p>
                        </div>

                        <!-- Jasna napomena za portfolio/demo -->
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-8 text-left max-w-2xl mx-auto">
                            <p class="text-sm text-yellow-700">
                                <strong>Note for portfolio/demo:</strong><br>
                                This is a simulated success page. Real PayPal capture failed due to Sandbox permission limitations (PERMISSION_DENIED error).  
                                In production (live mode) this would update to 'completed' and trigger email notifications/webhook.
                            </p>
                        </div>

                        <a href="/" class="inline-block bg-indigo-600 text-white px-8 py-4 rounded-lg text-lg hover:bg-indigo-700">
                            Back to Shop
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>