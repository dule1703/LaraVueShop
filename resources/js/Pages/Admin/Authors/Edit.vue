<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuthorForm from '@/Components/Admin/AuthorForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    author: Object,
});
</script>

<template>
    <Head title="Izmena autora" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h1 class="text-2xl font-bold mb-6">Izmena autora</h1>
                        <AuthorForm :author="author" :submit-url="route('admin.authors.update', author.id)" method="put" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
