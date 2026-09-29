<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { onMounted } from 'vue';
import { formatPrice } from '@/lib/bookLabels';

const cart = useCartStore();

onMounted(async () => {
  // MORA prvo load pa tek onda hydrate: hydrate() gleda trenutne cart.items,
  // a app.js učitava korpu (loadFromBackend/loadFromLocalStorage) TEK POSLE
  // app.mount()-a, tj. POSLE što se ovaj onMounted već izvršio. Osloniti se
  // na app.js-ov load bi značilo da hydrate() vidi praznu korpu, ništa ne
  // fetch-uje, i cena ostane 0,00 € zauvek (ne re-triggeruje se automatski
  // kad items kasnije stignu). Vidi resources/js/Stores/cart.test.js.
  await cart.loadFromBackend(); // gost -> interno pada nazad na loadFromLocalStorage()
  await cart.hydrate();
});

const increaseQuantity = (productId) => {
  cart.addItem(productId, 1);
};

const decreaseQuantity = (productId) => {
  cart.decreaseQuantity(productId);
};
</script>

<template>
  <Head title="Korpa" />

  <AuthenticatedLayout>
    <div class="py-6 md:py-12 bg-brand-page min-h-screen">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="font-serif text-3xl md:text-4xl font-semibold mb-6 md:mb-10 text-brand-text-primary text-center md:text-left">
          Vaša korpa
        </h1>

        <div v-if="cart.itemCount === 0" class="text-center py-16 text-brand-text-secondary text-lg">
          Vaša korpa je prazna.
          <div class="mt-6">
            <a href="/" class="text-brand-accent hover:text-brand-accent-hover font-medium">
              Nastavi kupovinu →
            </a>
          </div>
        </div>

        <div v-else-if="cart.isLoading" class="text-center py-16 text-brand-text-secondary text-lg">
          Učitavanje korpe...
        </div>

        <div v-else class="space-y-4 md:space-y-6">
          <div
            v-for="item in cart.items"
            :key="item.product_id"
            class="flex flex-col sm:flex-row sm:items-center justify-between rounded-2xl border border-black/5 bg-white p-4 md:p-6 shadow-sm gap-4 md:gap-6"
          >
            <!-- Product info -->
            <div class="flex-1">
              <h2 class="text-lg md:text-xl font-medium text-brand-text-primary">
                {{ cart.productDetails[item.product_id]?.name }}
              </h2>
              <p class="text-sm md:text-base text-brand-text-secondary mt-1">
                {{ formatPrice(cart.productDetails[item.product_id]?.price ?? 0) }}
              </p>
            </div>

            <!-- Quantity controls -->
            <div class="flex items-center justify-center sm:justify-end gap-1 sm:gap-2">
              <button
                @click="decreaseQuantity(item.product_id)"
                :disabled="item.quantity <= 1"
                class="w-9 h-9 md:w-10 md:h-10 flex items-center justify-center rounded-full border border-black/10 text-brand-text-primary hover:bg-brand-card disabled:opacity-40 disabled:cursor-not-allowed transition text-lg md:text-xl font-medium"
              >
                −
              </button>

              <span class="w-10 md:w-12 text-center font-medium text-lg md:text-xl text-brand-text-primary">
                {{ item.quantity }}
              </span>

              <button
                @click="increaseQuantity(item.product_id)"
                class="w-9 h-9 md:w-10 md:h-10 flex items-center justify-center rounded-full border border-black/10 text-brand-text-primary hover:bg-brand-card transition text-lg md:text-xl font-medium"
              >
                +
              </button>
            </div>

            <!-- Subtotal & Remove -->
            <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
              <p class="font-semibold text-brand-text-primary text-lg md:text-xl">
                {{ formatPrice((cart.productDetails[item.product_id]?.price ?? 0) * item.quantity) }}
              </p>

              <button
                @click="cart.removeItem(item.product_id)"
                class="text-red-600 hover:text-red-800 font-medium text-base md:text-lg transition"
              >
                Ukloni
              </button>
            </div>
          </div>

          <!-- Total & Checkout -->
          <div class="pt-6 md:pt-8 border-t border-black/10">
            <div class="flex justify-end text-xl md:text-2xl font-bold text-brand-text-primary">
              Ukupno: <span class="text-brand-accent ml-2">{{ formatPrice(cart.totalAmount) }}</span>
            </div>

            <div class="mt-6 md:mt-8 flex justify-center sm:justify-end">
              <a
                href="/checkout"
                class="inline-block rounded-full bg-brand-accent hover:bg-brand-accent-hover text-white font-semibold py-3 md:py-4 px-8 md:px-10 transition text-center w-full sm:w-auto text-base md:text-lg"
              >
                Nastavi na plaćanje
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
