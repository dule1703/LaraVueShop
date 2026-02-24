<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    order: Object,
    message: String, // opciono, ako želiš da proslediš specifičnu poruku iz kontrolera
});
</script>

<template>
    <Head title="Payment Failed" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-10 text-center">
                        <div class="text-red-600 text-8xl mb-8">✗</div>
                        <h1 class="text-4xl font-bold mb-6 text-red-600">Payment Failed</h1>

                        <p class="text-xl mb-8">
                            Sorry, your payment for order #{{ order.id }} could not be completed.
                        </p>

                        <div class="text-lg mb-10">
                            <p>Total amount attempted: <strong>{{ order.total_price }} €</strong></p>
                            <p class="mt-2">Status: <strong class="text-red-600">{{ order.status }}</strong></p>
                        </div>

                        <!-- Jasno objašnjenje za portfolio + korisnika -->
                        <div class="bg-red-50 border-l-4 border-red-400 p-6 mb-8 text-left max-w-3xl mx-auto">
                            <p class="text-sm text-red-700">
                                <strong>Note:</strong><br>
                                This is a test project using PayPal Sandbox mode. Payment capture failed due to PayPal Sandbox permission limitations (PERMISSION_DENIED error – common in test environments).  
                                In production (live Paypal mode) this would work with proper app verification and webhook support for reliable status updates.                                  
                            </p>
                        </div>

                        <div class="flex justify-center gap-6">
                            <a href="/checkout" class="inline-block bg-indigo-600 text-white px-8 py-4 rounded-lg text-lg hover:bg-indigo-700">
                                Try Again
                            </a>
                            <a href="/" class="inline-block bg-gray-600 text-white px-8 py-4 rounded-lg text-lg hover:bg-gray-700">
                                Back to Shop
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>