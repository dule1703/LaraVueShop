<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatPrice } from '@/lib/bookLabels';
import { paymentMethodLabels } from '@/lib/orderLabels';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    orders: Object,
});
</script>

<template>
    <Head title="Porudžbine" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-xl border border-brand-accent bg-brand-card p-4 text-brand-text-primary"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-6 rounded-xl border border-red-400 bg-red-50 p-4 text-red-700">
                    {{ page.props.flash.error }}
                </div>

                <AdminPageHeader title="Porudžbine" />

                <AdminTable
                    :headers="['ID', 'Kupac', 'Email', 'Ukupno', 'Status', 'Plaćanje', 'Datum', 'Akcije']"
                    :is-empty="orders.data.length === 0"
                    empty-message="Još nema porudžbina."
                >
                    <tr v-for="order in orders.data" :key="order.id">
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">#{{ order.id }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-primary">
                            {{ order.first_name }} {{ order.last_name }}
                            <span v-if="order.user" class="block text-sm text-brand-text-secondary">
                                ({{ order.user.name }})
                            </span>
                            <span v-else class="block text-sm text-brand-text-secondary">
                                (Gost)
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ order.customer_email }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ formatPrice(order.total_price) }}</td>
                        <td class="px-6 py-4">
                            <OrderStatusBadge :status="order.status" />
                        </td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">
                            {{ paymentMethodLabels[order.payment_method] ?? order.payment_method }}
                        </td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">
                            {{ new Date(order.created_at).toLocaleString('sr-RS') }}
                        </td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <Link
                                :href="route('admin.orders.show', order.id)"
                                class="font-medium text-brand-accent hover:text-brand-accent-hover"
                            >
                                Detalji
                            </Link>
                        </td>
                    </tr>
                </AdminTable>

                <Pagination :links="orders.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
