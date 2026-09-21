<script setup>
import { Link } from '@inertiajs/vue3';
import { availabilityLabel, formatLabels, formatPrice } from '@/lib/bookLabels';

defineProps({
    book: { type: Object, required: true },
});
</script>

<template>
    <Link
        :href="route('book.show', book.slug)"
        class="group flex flex-col bg-white border border-gray-100 rounded-2xl overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-200"
    >
        <div class="aspect-[3/4] bg-gray-100 overflow-hidden">
            <img
                v-if="book.image"
                :src="book.image"
                :alt="book.title"
                loading="lazy"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-sm text-gray-400">Bez korice</div>
        </div>
        <div class="flex flex-col flex-1 p-4">
            <h3 class="font-semibold text-gray-900 line-clamp-2 group-hover:text-indigo-600 transition-colors">
                {{ book.title }}
            </h3>
            <p v-if="book.authors.length" class="mt-1 text-sm text-gray-600 line-clamp-1">
                {{ book.authors.join(', ') }}
            </p>
            <p class="mt-1 text-xs text-gray-500">{{ formatLabels[book.format] ?? book.format }}</p>
            <div class="mt-auto pt-3 flex items-end justify-between">
                <span class="text-xl font-bold text-gray-900">{{ formatPrice(book.price) }}</span>
                <span class="text-xs font-medium" :class="book.available ? 'text-green-700' : 'text-red-600'">
                    {{ availabilityLabel(book) }}
                </span>
            </div>
        </div>
    </Link>
</template>
