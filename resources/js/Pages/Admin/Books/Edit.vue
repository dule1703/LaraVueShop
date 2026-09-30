<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import BookForm from '@/Components/Admin/BookForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    book: Object,
    categories: Array,
    publishers: Array,
    authors: Array,
    formats: Array,
    scripts: Array,
    roles: Array,
});
</script>

<template>
    <Head title="Izmena knjige" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Izmena knjige" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <BookForm
                        :book="book"
                        :options="{ categories, publishers, authors, formats, scripts, roles }"
                        :submit-url="route('admin.books.update', book.id)"
                        method="put"
                        submit-label="Sačuvaj izmene"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
