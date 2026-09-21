<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    publishers: Object,
});
</script>

<template>
    <Head title="Izdavači" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div v-if="page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ page.props.flash.success }}
                        </div>
                        <div v-if="page.props.flash?.error" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ page.props.flash.error }}
                        </div>

                        <h1 class="text-2xl font-bold mb-6">Izdavači</h1>

                        <Link :href="route('admin.publishers.create')" class="mb-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Dodaj izdavača
                        </Link>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Naziv</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Knjiga</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcije</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="publisher in publishers.data" :key="publisher.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ publisher.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ publisher.slug }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ publisher.books_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link :href="route('admin.publishers.edit', publisher.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">
                                                Izmeni
                                            </Link>
                                            <DeleteConfirmation
                                                :item-name="publisher.name"
                                                item-type="publisher"
                                                :delete-url="route('admin.publishers.destroy', publisher.id)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p v-if="publishers.data.length === 0" class="text-center py-8 text-gray-500">
                                Još nema izdavača. Dodaj prvog!
                            </p>
                        </div>

                        <Pagination :links="publishers.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
