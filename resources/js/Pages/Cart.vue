<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';

const cart = useCartStore();
cart.loadFromLocalStorage(); // učitaj korpu pri učitavanju stranice
</script>

<template>
    <Head title="Cart" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold mb-8">Your cart</h1>

                <div v-if="cart.itemCount === 0" class="text-center text-gray-600">
                    Cart is empty.
                </div>

                <div v-else class="space-y-6">
                    <div v-for="item in cart.items" :key="item.id" class="flex justify-between items-center border-b pb-4">
                        <div>
                            <h2 class="text-xl">{{ item.name }}</h2>
                            <p class="text-gray-600">{{ item.quantity }} × {{ item.price }} €</p>
                        </div>
                        <button @click="cart.removeItem(item.id)" class="text-red-600 hover:text-red-800">
                            Remove
                        </button>
                    </div>

                    <div class="text-right">
                        <p class="text-xl font-bold">Total: {{ cart.totalAmount }} €</p>
                    </div>
                </div>

                <a href="/checkout" class="block mt-8 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg text-center">
                    Buy now!
                </a>
            </div>
        </div>
    </AuthenticatedLayout>
</template>