<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h1 class="text-2xl font-bold mb-6">Nova knjiga</h1>
                        <BookForm
                            :options="{ categories, publishers, authors, formats, scripts, roles }"
                            :submit-url="route('admin.books.store')"
                            method="post"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
