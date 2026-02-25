<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
defineProps({
    orders: Object,  
});
</script>

<template>
    <Head title="Orders" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Flash poruka -->
                        <div v-if="page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ page.props.flash.success }}
                        </div>
                        <div v-if="page.props.flash?.error" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ page.props.flash.error }}
                        </div>

                        <h1 class="text-2xl font-bold mb-6">Orders</h1>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in orders.data" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #{{ order.id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ order.first_name }} {{ order.last_name }}
                                            <span v-if="order.user" class="text-sm text-gray-500 block">
                                                ({{ order.user.name }})
                                            </span>
                                            <span v-else class="text-sm text-gray-500 block">
                                                (Guest)
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ order.customer_email }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ Number(order.total_price).toFixed(2) }} €
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                      'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                                      'bg-green-100 text-green-800': order.status === 'paid' || order.status === 'completed',
                                                      'bg-blue-100 text-blue-800': order.status === 'processing' || order.status === 'shipped',
                                                      'bg-gray-100 text-gray-800': order.status === 'delivered',
                                                      'bg-red-100 text-red-800': order.status === 'cancelled' || order.status === 'failed'
                                                  }">
                                                {{ order.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="capitalize">{{ order.payment_method }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ new Date(order.created_at).toLocaleString('sr-RS') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link :href="route('admin.orders.show', order.id)" class="text-indigo-600 hover:text-indigo-900">
                                                Details
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Ako nema porudžbina -->
                            <p v-if="orders.data.length === 0" class="text-center py-8 text-gray-500">
                                No orders found.
                            </p>

                            <!-- ✅ ISPRAVLJENA Paginacija -->
                            <div v-if="orders.links" class="mt-6 flex justify-center">
                                <div class="flex items-center space-x-2">
                                    <template v-for="(link, index) in orders.links" :key="index">
                                        <!-- ✅ Ako link.url postoji - Link komponenta -->
                                        <Link 
                                            v-if="link.url"
                                            :href="link.url"
                                            :class="{
                                                'px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition': !link.active,
                                                'px-4 py-2 bg-blue-600 text-white rounded font-bold': link.active
                                            }"
                                            v-html="link.label"
                                        />
                                        <!-- ✅ Ako link.url NE postoji - obični span (disabled) -->
                                        <span 
                                            v-else
                                            class="px-4 py-2 bg-gray-100 text-gray-400 rounded cursor-not-allowed"
                                            v-html="link.label"
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>