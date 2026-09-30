<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AuthorForm from '@/Components/Admin/AuthorForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Novi autor" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Novi autor" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <AuthorForm :submit-url="route('admin.authors.store')" method="post" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
