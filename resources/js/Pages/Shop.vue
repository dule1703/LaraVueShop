<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Ili GuestLayout ako želiš bez auth
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    categories: Array,
});
</script>

<template>
    <Head title="Shop" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold mb-8">Our Products</h1>

                <div class="space-y-12">
                    <div v-for="category in props.categories" :key="category.id">
                        <h2 class="text-2xl font-semibold mb-4">{{ category.name }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <a v-for="product in category.products" :key="product.id" :href="route('product.details', product.id)" class="border rounded-lg p-4 hover:shadow-lg transition">
                                <img :src="product.image" alt="Product Image" class="w-full h-48 object-cover mb-4" />
                                <h3 class="text-xl font-medium mb-2">{{ product.name }}</h3>
                                <p class="text-gray-600">{{ product.price }} €</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>