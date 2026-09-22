<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { onMounted } from 'vue';
import { formatPrice } from '@/lib/bookLabels';

const cart = useCartStore();

onMounted(() => {
  cart.hydrate();
});

const increaseQuantity = (productId) => {
  cart.addItem(productId, 1);
};

const decreaseQuantity = (productId) => {
  cart.decreaseQuantity(productId);
};
</script>

<template>
  <Head title="Cart" />

  <AuthenticatedLayout>
    <div class="py-6 md:py-12 bg-gray-50 min-h-screen">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl md:text-4xl font-bold mb-6 md:mb-10 text-gray-900 text-center md:text-left">
          Your Cart
        </h1>

        <div v-if="cart.itemCount === 0" class="text-center py-16 text-gray-600 text-lg">
          Your cart is empty.
          <div class="mt-6">
            <a href="/" class="text-blue-600 hover:text-blue-800 font-medium">
              Continue shopping →
            </a>
          </div>
        </div>

        <div v-else-if="cart.isLoading" class="text-center py-16 text-gray-500 text-lg">
          Učitavanje korpe...
        </div>

        <div v-else class="space-y-6 md:space-y-8">
          <div
            v-for="item in cart.items"
            :key="item.product_id"
            class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-200 pb-6 last:border-b-0 gap-4 md:gap-6"
          >
            <!-- Product info -->
            <div class="flex-1">
              <h2 class="text-lg md:text-xl font-medium text-gray-900">
                {{ cart.productDetails[item.product_id]?.name }}
              </h2>
              <p class="text-sm md:text-base text-gray-600 mt-1">
                {{ formatPrice(cart.productDetails[item.product_id]?.price ?? 0) }}
              </p>
            </div>

            <!-- Quantity controls -->
            <div class="flex items-center justify-center sm:justify-end gap-1 sm:gap-2">
              <button
                @click="decreaseQuantity(item.product_id)"
                :disabled="item.quantity <= 1"
                class="w-9 h-9 md:w-10 md:h-10 flex items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition text-lg md:text-xl font-medium"
              >
                −
              </button>

              <span class="w-10 md:w-12 text-center font-medium text-lg md:text-xl">
                {{ item.quantity }}
              </span>

              <button
                @click="increaseQuantity(item.product_id)"
                class="w-9 h-9 md:w-10 md:h-10 flex items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition text-lg md:text-xl font-medium"
              >
                +
              </button>
            </div>

            <!-- Subtotal & Remove -->
            <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
              <p class="font-medium text-gray-900 text-lg md:text-xl">
                {{ formatPrice((cart.productDetails[item.product_id]?.price ?? 0) * item.quantity) }}
              </p>

              <button
                @click="cart.removeItem(item.product_id)"
                class="text-red-600 hover:text-red-800 font-medium text-base md:text-lg transition"
              >
                Remove
              </button>
            </div>
          </div>

          <!-- Total & Checkout -->
          <div class="pt-6 md:pt-8 border-t border-gray-200">
            <div class="flex justify-end text-xl md:text-2xl font-bold text-gray-900">
              Total: {{ formatPrice(cart.totalAmount) }}
            </div>

            <div class="mt-6 md:mt-8 flex justify-center sm:justify-end">
              <a
                href="/checkout"
                class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 md:py-4 px-8 md:px-10 rounded-lg transition text-center w-full sm:w-auto text-base md:text-lg"
              >
                Proceed to Checkout
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
