<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { ref } from 'vue';

const props = defineProps({
    product: Object,
});

const cart = useCartStore();
cart.loadFromLocalStorage(); // učitaj korpu

const quantity = ref(1);

const addToCart = () => {
    cart.addItem(props.product, quantity.value);
    quantity.value = 1;    
};
</script>

<template>
    <Head title="Product Details" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="grid md:grid-cols-2 gap-8">
                        <img :src="props.product.image" alt="Product Image" class="w-full h-auto rounded-lg" />
                        <div>
                            <h1 class="text-3xl font-bold mb-4">{{ props.product.name }}</h1>
                            <p class="text-2xl font-semibold mb-6">{{ props.product.price }} €</p>
                            <p class="text-gray-600 mb-8">{{ props.product.description }}</p>

                            <!-- Količina -->
                            <div class="flex items-center mb-4">
                                <label for="quantity" class="mr-4 text-gray-700">Quantity:</label>
                                <input
                                    v-model.number="quantity"
                                    type="number"
                                    id="quantity"
                                    min="1"
                                    class="w-16 rounded border border-gray-300 px-2 py-1 focus:border-blue-500 focus:outline-none"
                                />
                            </div>

                            <button @click="addToCart" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>