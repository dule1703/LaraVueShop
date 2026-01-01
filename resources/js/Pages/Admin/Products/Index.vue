<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue';

const page = usePage();

defineProps({
    products: Array,
});
</script>

<template>
    <Head title="Products" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Flash poruka -->
                        <div v-if="page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ page.props.flash.success }}
                        </div>

                        <h1 class="text-2xl font-bold mb-6">Products</h1>

                        <Link :href="route('admin.products.create')" class="mb-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Add New Product
                        </Link>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="product in products" :key="product.id">
                                        <td class="px-6 py-4">
                                            <img :src="product.image" alt="product.image" class="h-16 w-16 object-cover rounded">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ product.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ product.category.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ product.price }} €</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ product.stock }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="product.is_active ? 'text-green-600' : 'text-red-600'">
                                                {{ product.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link :href="route('admin.products.edit', product.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">
                                                Edit
                                            </Link>

                                            <DeleteConfirmation
                                                :item-name="product.name"
                                                item-type="product"
                                                :delete-url="route('admin.products.destroy', product.id)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p v-if="products.length === 0" class="text-center py-8 text-gray-500">
                                There are no products yet. Add the first one!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>