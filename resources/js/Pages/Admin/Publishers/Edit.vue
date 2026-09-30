<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import PublisherForm from '@/Components/Admin/PublisherForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    publisher: Object,
});
</script>

<template>
    <Head title="Izmena izdavača" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Izmena izdavača" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <PublisherForm
                        :publisher="publisher"
                        :submit-url="route('admin.publishers.update', publisher.id)"
                        method="put"
                        submit-label="Sačuvaj izmene"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
