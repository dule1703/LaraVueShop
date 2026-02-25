<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    order: Object,
});

const updateStatus = (newStatus) => {
    if (!confirm(`Are you sure you want to change status to "${newStatus}"?`)) return;

    router.patch(route('admin.orders.update', props.order.id), {
        status: newStatus,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            props.order.status = newStatus;  
        },
    });
};

const deleteOrder = () => {
    if (!confirm('Are you sure you want to delete this order? This action cannot be undone.')) return;

    router.delete(route('admin.orders.destroy', props.order.id), {
        onSuccess: () => {
            router.visit(route('admin.orders.index'));
        },
    });
};

</script>

<template>
    <Head title="Order Details #{{ order.id }}" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 lg:p-8">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                            <h1 class="text-3xl font-bold">Order #{{ order.id }}</h1>

                            <span class="px-6 py-3 rounded-full text-white font-semibold text-lg uppercase tracking-wide"
                                  :class="{
                                      'bg-yellow-500': order.status === 'pending',
                                      'bg-green-600': order.status === 'paid' || order.status === 'completed',
                                      'bg-red-600': order.status === 'failed' || order.status === 'cancelled',
                                      'bg-gray-500': order.status === 'processing',
                                  }">
                                {{ order.status }}
                            </span>
                        </div>

                        <!-- Buyer & Contact Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <h2 class="text-xl font-semibold mb-4">Buyer Information</h2>
                                <p><strong>Name:</strong> {{ order.first_name }}  {{ order.last_name }}</p>
                                <p><strong>Email:</strong> {{ order.customer_email }}</p>
                                <p><strong>Phone:</strong> {{ order.phone }}</p>
                                <p v-if="order.notes" class="mt-4 text-sm text-gray-600">
                                    <strong>Notes:</strong> {{ order.notes }}
                                </p><br>
                                <p v-if="order.user">
                                    <strong>Registered User:</strong> Yes (ID: {{ order.user.id }})
                                </p>
                                <p v-else>
                                    <strong>Registered User:</strong> No (Guest)
                                </p>
                            </div>

                            <div class="bg-gray-50 p-6 rounded-lg">
                                <h2 class="text-xl font-semibold mb-4">Shipping Address</h2>
                                <p><strong>Name:</strong> {{ order.first_name }}  {{ order.last_name }}</p>
                                <p><strong>Address:</strong> {{ order.address }}</p>
                                <p><strong>City:</strong> {{ order.city }}</p>
                                <p><strong>Postal Code:</strong> {{ order.postal_code }}</p>
                                <p><strong>Phone:</strong> {{ order.phone }}</p>
                                <p><strong>Email:</strong> {{ order.customer_email }}</p>
                                
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-12">
                            <h2 class="text-xl font-semibold mb-4">Order Items</h2>
                            <div class="border rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Product</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Price</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-500 uppercase">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="item in order.items" :key="item.id">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">{{ item.product_name }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ Number((item.product_price)).toFixed(2) }} €
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ item.quantity }}
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                {{ Number((item.product_price * item.quantity)).toFixed(2) }} €
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50">
                                            <td colspan="3" class="px-6 py-4 text-right font-bold text-lg">Total:</td>
                                            <td class="px-6 py-4 font-bold text-xl text-blue-700">
                                                {{ Number(order.total_price).toFixed(2) }} €
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-4 justify-end">
                            <button
                                v-if="order.status === 'pending'"
                                @click="updateStatus('paid')"
                                class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition"
                            >
                                Mark as Paid
                            </button>

                            <button
                                v-if="order.status === 'pending'"
                                @click="updateStatus('processing')"
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition"
                            >
                                Mark as Processing
                            </button>

                            <button
                                v-if="order.status === 'pending'"
                                @click="updateStatus('cancelled')"
                                class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition"
                            >
                                Cancel Order
                            </button>

                            <button
                                @click="deleteOrder"
                                class="bg-gray-800 text-white px-6 py-3 rounded-lg hover:bg-gray-900 transition"
                            >
                                Delete Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>