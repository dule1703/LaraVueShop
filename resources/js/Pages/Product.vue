<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { computed, ref } from 'vue';
import { availabilityLabel, formatLabels, formatPrice, languageLabel, roleLabels, scriptLabels } from '@/lib/bookLabels';

const props = defineProps({
    book: Object,
});

const cart = useCartStore();
const quantity = ref(1);
const added = ref(false);

// stock === null (e-knjiga) checkout još ne podržava — dugme ostaje onemogućeno (Faza 5).
const canBuy = computed(() => props.book.available && props.book.stock !== null);
const maxQuantity = computed(() => props.book.stock ?? 1);

const writers = computed(() => props.book.authors.filter((a) => a.role === 'author'));
const contributors = computed(() => props.book.authors.filter((a) => a.role !== 'author'));

const details = computed(() => [
    { label: 'Izdavač', value: props.book.publisher?.name, href: props.book.publisher ? route('shop', { publisher: props.book.publisher.slug }) : null },
    { label: 'ISBN', value: props.book.isbn13 },
    { label: 'Godina izdanja', value: props.book.published_year },
    { label: 'Broj strana', value: props.book.pages },
    { label: 'Jezik', value: languageLabel(props.book.language) },
    { label: 'Pismo', value: props.book.script ? scriptLabels[props.book.script] ?? props.book.script : null },
    { label: 'Format', value: formatLabels[props.book.format] ?? props.book.format },
    { label: 'Originalni naslov', value: props.book.original_title },
].filter((row) => row.value !== null && row.value !== undefined && row.value !== ''));

const addToCart = () => {
    const qty = Math.min(Math.max(Number(quantity.value) || 1, 1), maxQuantity.value);
    cart.addItem({
        id: props.book.product_id,
        name: props.book.title,
        price: props.book.price,
        image: props.book.image,
    }, qty);
    quantity.value = 1;
    added.value = true;
};
</script>

<template>
    <Head :title="book.title" />

    <AuthenticatedLayout>
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="mb-6 text-sm text-gray-500 flex flex-wrap gap-x-2">
                    <Link :href="route('shop')" class="hover:text-gray-900">Knjige</Link>
                    <template v-if="book.category">
                        <span>/</span>
                        <Link :href="route('shop', { category: book.category.slug })" class="hover:text-gray-900">{{ book.category.name }}</Link>
                    </template>
                </nav>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="md:col-span-1">
                            <img v-if="book.image" :src="book.image" :alt="book.title" class="w-full h-auto rounded-lg" />
                            <div v-else class="aspect-[3/4] rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">Bez korice</div>
                        </div>

                        <div class="md:col-span-2">
                            <h1 class="text-3xl font-bold text-gray-900">{{ book.title }}</h1>
                            <p v-if="book.subtitle" class="mt-1 text-lg text-gray-600">{{ book.subtitle }}</p>

                            <p v-if="writers.length" class="mt-3 text-gray-800">
                                <template v-for="(a, i) in writers" :key="a.slug">
                                    <span v-if="i">, </span>
                                    <Link :href="route('shop', { author: a.slug })" class="hover:underline">{{ a.name }}</Link>
                                </template>
                            </p>
                            <p v-if="contributors.length" class="mt-1 text-sm text-gray-600">
                                <template v-for="(a, i) in contributors" :key="a.slug + a.role">
                                    <span v-if="i">, </span>
                                    <Link :href="route('shop', { author: a.slug })" class="hover:underline">{{ a.name }}</Link>
                                    ({{ roleLabels[a.role] ?? a.role }})
                                </template>
                            </p>

                            <p class="mt-6 text-3xl font-semibold text-gray-900">{{ formatPrice(book.price) }}</p>
                            <p class="mt-1 text-sm font-medium" :class="book.available ? 'text-green-700' : 'text-red-600'">
                                {{ availabilityLabel(book) }}<template v-if="book.stock !== null && book.available"> ({{ book.stock }} kom.)</template>
                            </p>

                            <div class="mt-6 flex items-center gap-4">
                                <label for="quantity" class="text-gray-700">Količina:</label>
                                <input
                                    id="quantity"
                                    v-model.number="quantity"
                                    type="number"
                                    min="1"
                                    :max="maxQuantity"
                                    :disabled="!canBuy"
                                    class="border rounded px-3 py-2 w-20 disabled:bg-gray-100"
                                />
                                <button
                                    type="button"
                                    :disabled="!canBuy"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                                    @click="addToCart"
                                >
                                    Dodaj u korpu
                                </button>
                            </div>
                            <p v-if="book.stock === null" class="mt-2 text-sm text-gray-500">
                                Kupovina e-knjiga još nije omogućena.
                            </p>
                            <p v-if="added" class="mt-2 text-sm text-green-700">
                                Dodato u korpu. <Link :href="route('cart')" class="underline">Pogledaj korpu</Link>
                            </p>

                            <dl class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                                <div v-for="row in details" :key="row.label" class="flex justify-between border-b border-gray-100 py-1.5">
                                    <dt class="text-gray-500">{{ row.label }}</dt>
                                    <dd class="text-gray-900 text-right">
                                        <Link v-if="row.href" :href="row.href" class="hover:underline">{{ row.value }}</Link>
                                        <template v-else>{{ row.value }}</template>
                                    </dd>
                                </div>
                            </dl>

                            <p v-if="book.description" class="mt-8 text-gray-700 whitespace-pre-line">{{ book.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
