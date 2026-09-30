<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import Pagination from '@/Components/Pagination.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    authors: Object,
});
</script>

<template>
    <Head title="Autori" />

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

                <AdminPageHeader title="Autori">
                    <template #actions>
                        <Link
                            :href="route('admin.authors.create')"
                            class="inline-flex items-center rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-accent-hover"
                        >
                            + Dodaj autora
                        </Link>
                    </template>
                </AdminPageHeader>

                <AdminTable
                    :headers="['Fotografija', 'Ime', 'Slug', 'Knjiga', 'Akcije']"
                    :is-empty="authors.data.length === 0"
                    empty-message="Još nema autora. Dodaj prvog!"
                >
                    <tr v-for="author in authors.data" :key="author.id">
                        <td class="px-6 py-4">
                            <div class="h-12 w-12 overflow-hidden rounded-lg border border-black/10">
                                <BookCoverPlaceholder :title="author.name" :image="author.photo" />
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ author.name }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ author.slug }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-primary">{{ author.books_count }}</td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <Link
                                :href="route('admin.authors.edit', author.id)"
                                class="mr-4 font-medium text-brand-accent hover:text-brand-accent-hover"
                            >
                                Izmeni
                            </Link>
                            <DeleteConfirmation
                                :item-name="author.name"
                                item-type="author"
                                :delete-url="route('admin.authors.destroy', author.id)"
                            />
                        </td>
                    </tr>
                </AdminTable>

                <Pagination :links="authors.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
