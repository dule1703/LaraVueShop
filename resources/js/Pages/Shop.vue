<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCard from '@/Components/Catalog/BookCard.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { formatLabels, languageLabel, scriptLabels } from '@/lib/bookLabels';

const props = defineProps({
    books: Object,
    filters: Object,
    options: Object,
});

const PRICE_DEBOUNCE_MS = 400;

const form = reactive({ ...props.filters });
const showFilters = ref(false);
let priceDebounceTimer = null;

// Back/forward i "Poništi filtere" menjaju props bez remount-a komponente.
watch(() => props.filters, (filters) => Object.assign(form, filters));

watch([() => form.price_min, () => form.price_max], () => {
    clearTimeout(priceDebounceTimer);
    priceDebounceTimer = setTimeout(apply, PRICE_DEBOUNCE_MS);
});

const hasActiveFilters = computed(() => Object.values(props.filters).some((v) => v !== null && v !== false));

function apply() {
    clearTimeout(priceDebounceTimer);

    const params = {};
    for (const [key, value] of Object.entries(form)) {
        if (value === null || value === '' || value === false) continue;
        params[key] = value === true ? 1 : value;
    }

    // Bez `page` — svaka promena filtera vraća na prvu stranu.
    router.get(route('shop'), params, { preserveState: true, preserveScroll: true, replace: true });
}

function categoryLabel(category) {
    return String.fromCharCode(160).repeat(category.depth * 2) + category.name;
}
</script>

<template>
    <Head title="Knjige" />
    <AuthenticatedLayout>
        <div class="py-10 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Knjige</h1>
                        <p class="mt-1 text-sm text-gray-600">Pronađeno: {{ books.total }}</p>
                    </div>
                    <button
                        type="button"
                        class="lg:hidden px-4 py-2 text-sm font-medium border border-gray-300 rounded-md bg-white"
                        @click="showFilters = !showFilters"
                    >
                        Filteri
                    </button>
                </div>

                <div class="lg:grid lg:grid-cols-4 lg:gap-8">
                    <div
                        class="mb-8 lg:mb-0 space-y-4 lg:block"
                        :class="showFilters ? 'block' : 'hidden'"
                    >
                        <div>
                            <label for="f-category" class="block text-sm font-medium text-gray-700">Kategorija</label>
                            <select id="f-category" v-model="form.category" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Sve kategorije</option>
                                <option v-for="c in options.categories" :key="c.id" :value="c.slug">{{ categoryLabel(c) }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-author" class="block text-sm font-medium text-gray-700">Autor</label>
                            <select id="f-author" v-model="form.author" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Svi autori</option>
                                <option v-for="a in options.authors" :key="a.id" :value="a.slug">{{ a.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-publisher" class="block text-sm font-medium text-gray-700">Izdavač</label>
                            <select id="f-publisher" v-model="form.publisher" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Svi izdavači</option>
                                <option v-for="p in options.publishers" :key="p.id" :value="p.slug">{{ p.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-language" class="block text-sm font-medium text-gray-700">Jezik</label>
                            <select id="f-language" v-model="form.language" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Svi jezici</option>
                                <option v-for="l in options.languages" :key="l" :value="l">{{ languageLabel(l) }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-script" class="block text-sm font-medium text-gray-700">Pismo</label>
                            <select id="f-script" v-model="form.script" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Sva pisma</option>
                                <option v-for="s in options.scripts" :key="s" :value="s">{{ scriptLabels[s] ?? s }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-format" class="block text-sm font-medium text-gray-700">Format</label>
                            <select id="f-format" v-model="form.format" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm" @change="apply">
                                <option :value="null">Svi formati</option>
                                <option v-for="f in options.formats" :key="f" :value="f">{{ formatLabels[f] ?? f }}</option>
                            </select>
                        </div>

                        <div>
                            <span class="block text-sm font-medium text-gray-700">Cena (€)</span>
                            <div class="mt-1 flex items-center gap-2">
                                <input v-model="form.price_min" type="number" min="0" step="0.01" placeholder="od" aria-label="Cena od" class="block w-full rounded-md border border-gray-300 bg-white text-sm" />
                                <span class="text-gray-400">–</span>
                                <input v-model="form.price_max" type="number" min="0" step="0.01" placeholder="do" aria-label="Cena do" class="block w-full rounded-md border border-gray-300 bg-white text-sm" />
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.in_stock" type="checkbox" class="rounded border-gray-300" @change="apply" />
                            Samo na stanju
                        </label>

                        <div v-if="hasActiveFilters" class="flex items-center gap-3">
                            <Link :href="route('shop')" class="text-sm text-gray-600 underline hover:text-gray-900">
                                Poništi filtere
                            </Link>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <div v-if="books.data.length" class="grid grid-cols-2 sm:grid-cols-3 gap-4 sm:gap-6">
                            <BookCard v-for="book in books.data" :key="book.slug" :book="book" />
                        </div>
                        <div v-else class="py-16 text-center text-gray-600">
                            Nema knjiga koje odgovaraju izabranim filterima.
                        </div>

                        <Pagination :links="books.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
