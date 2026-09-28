<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';
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
    cart.addItem(props.book.product_id, qty);
    quantity.value = 1;
    added.value = true;
};
</script>

<template>
    <Head :title="book.title" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-10">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <nav class="mb-6 flex flex-wrap gap-x-2 text-sm text-brand-text-secondary">
                    <Link :href="route('shop')" class="hover:text-brand-accent">Knjige</Link>
                    <template v-if="book.category">
                        <span>/</span>
                        <Link :href="route('shop', { category: book.category.slug })" class="hover:text-brand-accent">{{ book.category.name }}</Link>
                    </template>
                </nav>

                <div class="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                    <div class="grid gap-8 md:grid-cols-5">
                        <div class="md:col-span-2">
                            <div class="aspect-[3/4] overflow-hidden rounded-xl">
                                <BookCoverPlaceholder
                                    :title="book.title"
                                    :author="writers.map((a) => a.name).join(', ')"
                                    :image="book.image"
                                />
                            </div>
                        </div>

                        <div class="md:col-span-3">
                            <h1 class="font-serif text-3xl font-semibold text-brand-text-primary">{{ book.title }}</h1>
                            <p v-if="book.subtitle" class="mt-1 text-lg text-brand-text-secondary">{{ book.subtitle }}</p>

                            <p v-if="writers.length" class="mt-3 text-brand-text-primary">
                                <template v-for="(a, i) in writers" :key="a.slug">
                                    <span v-if="i">, </span>
                                    <Link :href="route('shop', { author: a.slug })" class="hover:text-brand-accent hover:underline">{{ a.name }}</Link>
                                </template>
                            </p>
                            <p v-if="contributors.length" class="mt-1 text-sm text-brand-text-secondary">
                                <template v-for="(a, i) in contributors" :key="a.slug + a.role">
                                    <span v-if="i">, </span>
                                    <Link :href="route('shop', { author: a.slug })" class="hover:text-brand-accent hover:underline">{{ a.name }}</Link>
                                    ({{ roleLabels[a.role] ?? a.role }})
                                </template>
                            </p>

                            <p class="mt-6 text-3xl font-bold text-brand-text-primary">{{ formatPrice(book.price) }}</p>
                            <p class="mt-1 text-sm font-medium" :class="book.available ? 'text-emerald-700' : 'text-red-600'">
                                {{ availabilityLabel(book) }}<template v-if="book.stock !== null && book.available"> ({{ book.stock }} kom.)</template>
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-4">
                                <label for="quantity" class="text-brand-text-primary">Količina:</label>
                                <input
                                    id="quantity"
                                    v-model.number="quantity"
                                    type="number"
                                    min="1"
                                    :max="maxQuantity"
                                    :disabled="!canBuy"
                                    class="w-20 rounded-lg border border-black/10 px-3 py-2 text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent disabled:bg-brand-card"
                                />
                                <button
                                    type="button"
                                    :disabled="!canBuy"
                                    class="whitespace-nowrap rounded-full bg-brand-accent px-6 py-2.5 font-semibold text-white transition hover:bg-brand-accent-hover disabled:cursor-not-allowed disabled:bg-brand-header-muted"
                                    @click="addToCart"
                                >
                                    Dodaj u korpu
                                </button>
                            </div>
                            <p v-if="book.stock === null" class="mt-2 text-sm text-brand-text-secondary">
                                Kupovina e-knjiga još nije omogućena.
                            </p>
                            <p v-if="added" class="mt-2 text-sm text-emerald-700">
                                Dodato u korpu. <Link :href="route('cart')" class="underline hover:text-brand-accent">Pogledaj korpu</Link>
                            </p>

                            <dl class="mt-8 grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                                <div v-for="row in details" :key="row.label" class="flex justify-between border-b border-black/10 py-1.5">
                                    <dt class="text-brand-text-secondary">{{ row.label }}</dt>
                                    <dd class="text-right text-brand-text-primary">
                                        <Link v-if="row.href" :href="row.href" class="hover:text-brand-accent hover:underline">{{ row.value }}</Link>
                                        <template v-else>{{ row.value }}</template>
                                    </dd>
                                </div>
                            </dl>

                            <p v-if="book.description" class="mt-8 max-w-prose whitespace-pre-line leading-relaxed text-brand-text-primary">{{ book.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
