<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import RestockForm from '@/Components/Admin/RestockForm.vue';
import Pagination from '@/Components/Pagination.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';
import { formatPrice } from '@/lib/bookLabels';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    books: Object,
    filters: Object,
});

const isLowStock = (book) => book.product.stock !== null && book.product.stock < props.filters.threshold;
</script>

<template>
    <Head title="Knjige" />

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

                <AdminPageHeader title="Knjige">
                    <template #actions>
                        <Link
                            :href="route('admin.books.index', filters.low_stock ? {} : { low_stock: 1 })"
                            class="inline-flex items-center rounded-lg border px-4 py-2 text-sm font-semibold transition"
                            :class="filters.low_stock ? 'border-amber-400 bg-amber-100 text-amber-800 hover:bg-amber-200' : 'border-black/20 bg-white text-brand-text-primary hover:bg-brand-card'"
                        >
                            {{ filters.low_stock ? `Niska zaliha (< ${filters.threshold}) — prikaži sve` : `Prikaži nisku zalihu (< ${filters.threshold})` }}
                        </Link>
                        <Link
                            :href="route('admin.books.create')"
                            class="inline-flex items-center rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-accent-hover"
                        >
                            + Dodaj knjigu
                        </Link>
                    </template>
                </AdminPageHeader>

                <AdminTable
                    :headers="['Slika', 'Naslov', 'Autori', 'ISBN', 'Cena', 'Zaliha', 'Status', 'Akcije']"
                    :is-empty="books.data.length === 0"
                    empty-message="Još nema knjiga. Dodaj prvu!"
                >
                    <tr v-for="book in books.data" :key="book.id" :class="{ 'bg-amber-50': isLowStock(book) }">
                        <td class="px-6 py-4">
                            <div class="h-12 w-12 overflow-hidden rounded-lg border border-black/10">
                                <BookCoverPlaceholder :title="book.product.name" :image="book.product.image" />
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ book.product.name }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">
                            {{ book.authors.map((a) => a.name).join(', ') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ book.isbn13 }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-primary">{{ formatPrice(book.product.price) }}</td>
                        <td class="px-6 py-4 text-sm" :class="isLowStock(book) ? 'font-semibold text-amber-700' : 'text-brand-text-primary'">
                            {{ book.product.stock ?? '∞' }}
                        </td>
                        <td class="px-6 py-4">
                            <StatusBadge :active="book.product.is_active" active-label="Aktivna" inactive-label="Neaktivna" />
                        </td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <Link
                                :href="route('admin.books.edit', book.id)"
                                class="mr-4 font-medium text-brand-accent hover:text-brand-accent-hover"
                            >
                                Izmeni
                            </Link>
                            <span v-if="book.product.stock !== null" class="mr-4">
                                <RestockForm
                                    :book-id="book.id"
                                    :book-name="book.product.name"
                                    :restock-url="route('admin.books.restock', book.id)"
                                />
                            </span>
                            <DeleteConfirmation
                                :item-name="book.product.name"
                                item-type="book"
                                :delete-url="route('admin.books.destroy', book.id)"
                            />
                        </td>
                    </tr>
                </AdminTable>

                <Pagination :links="books.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
