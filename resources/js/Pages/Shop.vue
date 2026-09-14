<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    categories: Array,
});
</script>

<template>
    <Head title="Shop" />
    <AuthenticatedLayout>
        <div class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">                    
                    <h1 class="text-5xl font-bold tracking-tight text-gray-900">Our Products</h1>
                    <h1 class="text-5xl font-bold tracking-tight text-gray-900">Naši proizvodi su ubedljivo najbolji!</h1>
                    <p class="mt-4 text-xl text-gray-600">Premium quality • Fast shipping • Best prices</p>
                </div>

                <div class="space-y-16">
                    <div v-for="category in props.categories" :key="category.id">
                        <h2 class="text-3xl font-bold mb-8 flex items-center gap-x-4">
                            {{ category.name }}
                            <span class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></span>
                        </h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                            <a 
                                v-for="product in category.products" 
                                :key="product.id" 
                                :href="route('product.details', product.id)" 
                                class="group bg-white border border-gray-100 rounded-3xl overflow-hidden hover:shadow-2xl hover:-translate-y-3 transition-all duration-300"
                            >
                                <div class="relative">
                                    <img 
                                        :src="product.image" 
                                        alt="Product Image" 
                                        class="w-full h-64 object-cover transition-transform duration-500 group-hover:scale-110" 
                                    />
                                </div>
                                <div class="p-6">
                                    <h3 class="text-xl font-semibold text-gray-900 mb-3 line-clamp-2 group-hover:text-[#FF2D20] transition-colors">
                                        {{ product.name }}
                                    </h3>
                                    <p class="text-3xl font-bold text-[#FF2D20]">{{ product.price }} €</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>