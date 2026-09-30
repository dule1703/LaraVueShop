<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AuthorForm from '@/Components/Admin/AuthorForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    author: Object,
});
</script>

<template>
    <Head title="Izmena autora" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Izmena autora" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <AuthorForm
                        :author="author"
                        :submit-url="route('admin.authors.update', author.id)"
                        method="put"
                        submit-label="Sačuvaj izmene"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
