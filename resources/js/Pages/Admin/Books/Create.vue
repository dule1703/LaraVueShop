<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import BookForm from '@/Components/Admin/BookForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    categories: Array,
    publishers: Array,
    authors: Array,
    formats: Array,
    scripts: Array,
    roles: Array,
});
</script>

<template>
    <Head title="Nova knjiga" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Nova knjiga" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <BookForm
                        :options="{ categories, publishers, authors, formats, scripts, roles }"
                        :submit-url="route('admin.books.store')"
                        method="post"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
