<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';   
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';


// Sada možeš koristiti usePage()
const page = usePage();

defineProps({
    categories: {
        type: Array,
        default: () => []
    }
});

</script>

<template>
    <Head title="Kategorije" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Flash poruka -->
                        <div v-if="page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ page.props.flash.success }}
                        </div>
                        <h1 class="text-2xl font-bold mb-6">Kategorije</h1>

                        <Link :href="route('admin.categories.create')" class="mb-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Dodaj novu kategoriju
                        </Link>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Naziv</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcije</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="category in categories" :key="category.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ category.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ category.slug }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="category.is_active ? 'text-green-600' : 'text-red-600'">
                                                {{ category.is_active ? 'Aktivna' : 'Neaktivna' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                           <Link :href="route('admin.categories.edit', category.id)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-4">
                                                Izmeni
                                            </Link>
                                            <DeleteConfirmation
                                                :item-name="category.name"
                                                item-type="kategoriju"
                                                :delete-url="route('admin.categories.destroy', category.id)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p v-if="categories.length === 0" class="text-center py-8 text-gray-500">
                                Još nema kategorija. Dodajte prvu!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>