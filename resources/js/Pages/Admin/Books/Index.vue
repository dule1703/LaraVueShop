<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    books: Object,
});
</script>

<template>
    <Head title="Knjige" />

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

                        <h1 class="text-2xl font-bold mb-6">Knjige</h1>

                        <Link :href="route('admin.books.create')" class="mb-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Dodaj knjigu
                        </Link>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Naslov</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Autori</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ISBN</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cena</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zaliha</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcije</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="book in books.data" :key="book.id">
                                        <td class="px-6 py-4">{{ book.product.name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ book.authors.map((a) => a.name).join(', ') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ book.isbn13 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">€{{ book.product.price }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ book.product.stock ?? '∞' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="book.product.is_active ? 'text-green-600' : 'text-red-600'">
                                                {{ book.product.is_active ? 'Aktivna' : 'Neaktivna' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link :href="route('admin.books.edit', book.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">
                                                Izmeni
                                            </Link>
                                            <DeleteConfirmation
                                                :item-name="book.product.name"
                                                item-type="book"
                                                :delete-url="route('admin.books.destroy', book.id)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p v-if="books.data.length === 0" class="text-center py-8 text-gray-500">
                                Još nema knjiga. Dodaj prvu!
                            </p>
                        </div>

                        <Pagination :links="books.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
