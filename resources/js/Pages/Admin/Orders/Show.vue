<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatPrice } from '@/lib/bookLabels';
import { orderStatusLabels, orderStatusTransitions } from '@/lib/orderLabels';

const props = defineProps({
    order: Object,
});

const nextStatuses = computed(() => orderStatusTransitions[props.order.status] ?? []);

// Pre "Cart/Checkout redizajn + sačuvane adrese" (Faza 6, korak 4) porudžbine
// nemaju shipping_* snapshot (kolone su nullable) — fallback na stare
// first_name/last_name/address/city/postal_code/phone da se prikaz ne
// ostavi prazan za istorijske porudžbine.
const hasShippingSnapshot = computed(() => Boolean(props.order.shipping_line1));

const updateStatus = (newStatus) => {
    if (!confirm(`Da li ste sigurni da želite da promenite status u "${orderStatusLabels[newStatus] ?? newStatus}"?`)) return;

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
</script>

<template>
    <Head :title="`Porudžbina #${order.id}`" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader :title="`Porudžbina #${order.id}`">
                    <template #actions>
                        <OrderStatusBadge :status="order.status" />
                        <DeleteConfirmation
                            item-name="ovu porudžbinu"
                            item-type="order"
                            :delete-url="route('admin.orders.destroy', order.id)"
                        />
                    </template>
                </AdminPageHeader>

                <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 font-serif text-lg font-semibold text-brand-text-primary">Podaci o kupcu</h2>
                        <dl class="space-y-2 text-sm text-brand-text-primary">
                            <div><dt class="inline font-semibold">Ime:</dt> <dd class="inline">{{ order.first_name }} {{ order.last_name }}</dd></div>
                            <div><dt class="inline font-semibold">Email:</dt> <dd class="inline">{{ order.customer_email }}</dd></div>
                            <div><dt class="inline font-semibold">Telefon:</dt> <dd class="inline">{{ order.phone }}</dd></div>
                            <div><dt class="inline font-semibold">Registrovan korisnik:</dt> <dd class="inline">{{ order.user ? `Da (ID: ${order.user.id})` : 'Ne (gost)' }}</dd></div>
                            <div v-if="order.notes" class="pt-2 text-brand-text-secondary">
                                <dt class="font-semibold text-brand-text-primary">Napomena:</dt>
                                <dd>{{ order.notes }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 font-serif text-lg font-semibold text-brand-text-primary">Adresa isporuke</h2>
                        <dl v-if="hasShippingSnapshot" class="space-y-2 text-sm text-brand-text-primary">
                            <div><dt class="inline font-semibold">Ime:</dt> <dd class="inline">{{ order.shipping_recipient_name }}</dd></div>
                            <div><dt class="inline font-semibold">Adresa:</dt> <dd class="inline">{{ order.shipping_line1 }}<span v-if="order.shipping_line2">, {{ order.shipping_line2 }}</span></dd></div>
                            <div><dt class="inline font-semibold">Grad:</dt> <dd class="inline">{{ order.shipping_city }}</dd></div>
                            <div><dt class="inline font-semibold">Poštanski broj:</dt> <dd class="inline">{{ order.shipping_postal_code }}</dd></div>
                            <div><dt class="inline font-semibold">Država:</dt> <dd class="inline">{{ order.shipping_country }}</dd></div>
                            <div><dt class="inline font-semibold">Telefon:</dt> <dd class="inline">{{ order.shipping_phone }}</dd></div>
                        </dl>
                        <dl v-else class="space-y-2 text-sm text-brand-text-primary">
                            <p class="mb-2 text-xs text-brand-text-secondary">
                                Porudžbina bez sačuvanog snapshot-a adrese (starija od uvođenja sačuvanih adresa) — prikaz iz starih polja.
                            </p>
                            <div><dt class="inline font-semibold">Ime:</dt> <dd class="inline">{{ order.first_name }} {{ order.last_name }}</dd></div>
                            <div><dt class="inline font-semibold">Adresa:</dt> <dd class="inline">{{ order.address }}</dd></div>
                            <div><dt class="inline font-semibold">Grad:</dt> <dd class="inline">{{ order.city }}</dd></div>
                            <div><dt class="inline font-semibold">Poštanski broj:</dt> <dd class="inline">{{ order.postal_code }}</dd></div>
                            <div><dt class="inline font-semibold">Telefon:</dt> <dd class="inline">{{ order.phone }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="mb-8 overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
                    <h2 class="border-b border-black/5 px-6 py-4 font-serif text-lg font-semibold text-brand-text-primary">Stavke porudžbine</h2>
                    <table class="min-w-full divide-y divide-black/5">
                        <thead class="bg-brand-card">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">Proizvod</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">Cena</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">Količina</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-text-secondary">Ukupno</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5">
                            <tr v-for="item in order.items" :key="item.id">
                                <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ item.product_name }}</td>
                                <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ formatPrice(item.product_price) }}</td>
                                <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ formatPrice(item.product_price * item.quantity) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-brand-card">
                                <td colspan="3" class="px-6 py-4 text-right font-serif text-lg font-semibold text-brand-text-primary">Ukupno:</td>
                                <td class="px-6 py-4 font-serif text-xl font-semibold text-brand-accent">{{ formatPrice(order.total_price) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        v-for="status in nextStatuses"
                        :key="status"
                        @click="updateStatus(status)"
                        class="inline-flex items-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition"
                        :class="status === 'cancelled' ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-accent hover:bg-brand-accent-hover'"
                    >
                        Označi kao „{{ orderStatusLabels[status] ?? status }}”
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
