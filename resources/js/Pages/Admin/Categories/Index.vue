<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const page = usePage();

defineProps({
    categories: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <Head title="Kategorije" />

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

                <AdminPageHeader title="Kategorije">
                    <template #actions>
                        <Link
                            :href="route('admin.categories.create')"
                            class="inline-flex items-center rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-accent-hover"
                        >
                            + Dodaj kategoriju
                        </Link>
                    </template>
                </AdminPageHeader>

                <AdminTable
                    :headers="['Naziv', 'Slug', 'Status', 'Akcije']"
                    :is-empty="categories.length === 0"
                    empty-message="Još nema kategorija. Dodajte prvu!"
                >
                    <tr v-for="category in categories" :key="category.id">
                        <td class="px-6 py-4 text-sm font-medium text-brand-text-primary">{{ category.name }}</td>
                        <td class="px-6 py-4 text-sm text-brand-text-secondary">{{ category.slug }}</td>
                        <td class="px-6 py-4">
                            <StatusBadge :active="category.is_active" />
                        </td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <Link
                                :href="route('admin.categories.edit', category.id)"
                                class="mr-4 font-medium text-brand-accent hover:text-brand-accent-hover"
                            >
                                Izmeni
                            </Link>
                            <DeleteConfirmation
                                :item-name="category.name"
                                item-type="category"
                                :delete-url="route('admin.categories.destroy', category.id)"
                            />
                        </td>
                    </tr>
                </AdminTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
