<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';
import { formatPrice } from '@/lib/bookLabels';

const page = usePage();

defineProps({
    products: Array,
});
</script>

<template>
    <Head title="Proizvodi" />

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

                <AdminPageHeader title="Proizvodi">
                    <template #actions>
                        <Link
                            :href="route('admin.products.create')"
                            class="inline-flex items-center rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-accent-hover"
                        >
                            + Dodaj proizvod
                        </Link>
                    </template>
                </AdminPageHeader>

                <AdminTable
                    :headers="['Slika', 'Naziv', 'Kategorija', 'Cena', 'Zaliha', 'Status', 'Akcije']"
                    :is-empty="products.length === 0"
                    empty-message="Još nema proizvoda. Dodajte prvi!"
                >
                    <tr v-for="product in products" :key="product.id">
                        <td class="px-6 py-4">
                            <div class="h-12 w-12 overflow-hidden rounded-lg border border-black/10">
                                <BookCoverPlaceholder :title="product.name" :image="product.image" />
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ product.name }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ product.category.name }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-primary">{{ formatPrice(product.price) }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-primary">{{ product.stock }}</td>
                        <td class="px-6 py-4">
                            <StatusBadge :active="product.is_active" />
                        </td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <Link
                                :href="route('admin.products.edit', product.id)"
                                class="mr-4 font-medium text-brand-accent hover:text-brand-accent-hover"
                            >
                                Izmeni
                            </Link>
                            <DeleteConfirmation
                                :item-name="product.name"
                                item-type="product"
                                :delete-url="route('admin.products.destroy', product.id)"
                            />
                        </td>
                    </tr>
                </AdminTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
