<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PublisherForm from '@/Components/Admin/PublisherForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Novi izdavač" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h1 class="text-2xl font-bold mb-6">Novi izdavač</h1>
                        <PublisherForm :submit-url="route('admin.publishers.store')" method="post" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
