<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { availabilityLabel, formatLabels, formatPrice } from '@/lib/bookLabels';
import { useCartStore } from '@/Stores/cart';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';

const props = defineProps({
    book: { type: Object, required: true },
});

const cart = useCartStore();
const justAdded = ref(false);
let resetJustAddedTimer = null;

// stock === null (e-knjiga) checkout još ne podržava — dugme ostaje onemogućeno (Faza 5).
const canBuy = computed(() => props.book.available && props.book.stock !== null);

function addToCart() {
    if (!canBuy.value) return;
    cart.addItem(props.book.product_id, 1);

    // "Dodato ✓" na 1.5s — dugme NIJE onemogućeno u tom prozoru (ponovni klik
    // odmah dodaje još jedan primerak i samo resetuje tajmer).
    justAdded.value = true;
    clearTimeout(resetJustAddedTimer);
    resetJustAddedTimer = setTimeout(() => { justAdded.value = false; }, 1500);
}

onUnmounted(() => clearTimeout(resetJustAddedTimer));
</script>

<template>
    <div class="group flex h-full flex-col overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
        <Link :href="route('book.show', book.slug)" tabindex="-1" aria-hidden="true" class="block aspect-[3/4] overflow-hidden">
            <BookCoverPlaceholder
                :title="book.title"
                :author="book.authors.join(', ')"
                :image="book.image"
                class="transition-transform duration-300 group-hover:scale-105"
            />
        </Link>
        <div class="flex flex-1 flex-col p-4">
            <Link :href="route('book.show', book.slug)">
                <h3 class="font-serif text-base font-semibold text-brand-text-primary line-clamp-2 transition-colors group-hover:text-brand-accent">
                    {{ book.title }}
                </h3>
            </Link>
            <p v-if="book.authors.length" class="mt-1 text-sm text-brand-text-secondary line-clamp-1">
                {{ book.authors.join(', ') }}
            </p>
            <p class="mt-1 text-xs text-brand-text-secondary">{{ formatLabels[book.format] ?? book.format }}</p>

            <div class="mt-auto flex flex-col gap-2 pt-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="whitespace-nowrap text-lg font-bold text-brand-text-primary">{{ formatPrice(book.price) }}</span>
                    <span class="whitespace-nowrap text-xs font-medium" :class="book.available ? 'text-emerald-700' : 'text-red-600'">
                        {{ availabilityLabel(book) }}
                    </span>
                </div>
                <button
                    type="button"
                    :disabled="!canBuy"
                    class="w-full rounded-full bg-brand-accent px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-accent-hover disabled:cursor-not-allowed disabled:bg-brand-header-muted"
                    @click="addToCart"
                >
                    {{ justAdded ? 'Dodato ✓' : 'U korpu' }}
                </button>
            </div>
        </div>
    </div>
</template>
