<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCard from '@/Components/Catalog/BookCard.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { formatLabels, languageLabel, scriptLabels } from '@/lib/bookLabels';

const props = defineProps({
    books: Object,
    filters: Object,
    options: Object,
});

const PRICE_DEBOUNCE_MS = 400;

const form = reactive({ ...props.filters });
const showFilters = ref(false);
let debounceTimer = null;

// `md:` breakpoint (Tailwind default 768px) — na desktopu filter panel MORA
// ostati u toku dokumenta (statična kolona u md:grid-u), pa se tamo Teleport
// isključuje preko `:disabled`; samo na mobilnom se stvarno teleportuje u
// <body> kao overlay/drawer. Bez ovoga bi Teleport uvek premestio panel u
// <body>, i na desktopu bi nestao iz grid layout-a.
const isDesktopFilters = ref(true);
let filtersMql = null;

function syncIsDesktopFilters(e) {
    isDesktopFilters.value = e.matches;
}

onMounted(() => {
    filtersMql = window.matchMedia('(min-width: 768px)');
    isDesktopFilters.value = filtersMql.matches;
    filtersMql.addEventListener('change', syncIsDesktopFilters);
});

// Back/forward i "Poništi filtere" menjaju props bez remount-a komponente.
watch(() => props.filters, (filters) => Object.assign(form, filters));

watch([() => form.price_min, () => form.price_max, () => form.search], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(apply, PRICE_DEBOUNCE_MS);
});

const hasActiveFilters = computed(() => Object.values(props.filters).some((v) => v !== null && v !== false));

function apply() {
    clearTimeout(debounceTimer);

    const params = {};
    for (const [key, value] of Object.entries(form)) {
        if (value === null || value === '' || value === false) continue;
        params[key] = value === true ? 1 : value;
    }

    // Bez `page` — svaka promena filtera vraća na prvu stranu.
    router.get(route('shop'), params, { preserveState: true, preserveScroll: true, replace: true });
}

function applyAndClose() {
    apply();
    showFilters.value = false;
}

function categoryLabel(category) {
    return String.fromCharCode(160).repeat(category.depth * 2) + category.name;
}

function closeOnEscape(e) {
    if (e.key === 'Escape' && showFilters.value) {
        showFilters.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    filtersMql?.removeEventListener('change', syncIsDesktopFilters);
});
</script>

<template>
    <Head title="Knjige" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h1 class="font-serif text-3xl font-semibold tracking-tight text-brand-text-primary">Knjige</h1>
                        <p class="mt-1 text-sm text-brand-text-secondary">Pronađeno: {{ books.total }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-black/10 bg-white px-4 py-2 text-sm font-medium text-brand-text-primary shadow-sm md:hidden"
                        @click="showFilters = true"
                    >
                        Filteri
                        <span v-if="hasActiveFilters" class="inline-flex h-2 w-2 rounded-full bg-brand-accent"></span>
                    </button>
                </div>

                <div class="mb-6">
                    <label for="f-search" class="sr-only">Pretraga</label>
                    <input
                        id="f-search"
                        v-model="form.search"
                        type="search"
                        placeholder="Pretraži naslov, autora, izdavača..."
                        class="block w-full rounded-full border border-black/10 bg-white px-4 py-2.5 text-sm text-brand-text-primary placeholder:text-brand-text-secondary focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent"
                        @keyup.enter="apply"
                    />
                </div>

                <div class="md:grid md:grid-cols-4 md:gap-8">
                    <!-- Desktop (md+, isDesktopFilters=true): Teleport je DISABLED preko
                         :disabled — panel ostaje na svom mestu u md:grid-u kao statična kolona
                         (Teleport ne razlikuje breakpoint-ove sam, mora se ručno isključiti,
                         inače bi ista <div> uvek završila u <body> i desktop grid bi izgubio
                         bočnu kolonu). Mobilni (< md): Teleport ENABLED — overlay + drawer se
                         stvarno premeste u <body>, van AuthenticatedLayout-ovog <nav> stacking
                         konteksta, pa pouzdano prekrivaju nav bez oslanjanja na DOM redosled/
                         z-index tie-breaking (raniji pristup bez Teleport-a). -->
                    <Teleport to="body" :disabled="isDesktopFilters">
                    <Transition
                        enter-active-class="transition-opacity duration-200"
                        enter-from-class="opacity-0"
                        leave-active-class="transition-opacity duration-150"
                        leave-to-class="opacity-0"
                    >
                        <div
                            v-if="showFilters"
                            class="fixed inset-0 z-50 bg-black/40 md:hidden"
                            @click="showFilters = false"
                        ></div>
                    </Transition>

                    <div
                        :role="isDesktopFilters ? undefined : 'dialog'"
                        :aria-modal="isDesktopFilters ? undefined : 'true'"
                        aria-label="Filteri"
                        class="space-y-5 overflow-y-auto bg-brand-page p-5 transition-transform duration-300 ease-out md:visible md:static md:z-auto md:h-auto md:w-auto md:translate-x-0 md:bg-transparent md:p-0 md:shadow-none md:transition-none"
                        :class="showFilters
                            ? 'fixed inset-y-0 right-0 z-50 w-full max-w-xs translate-x-0 shadow-2xl'
                            : 'invisible fixed inset-y-0 right-0 z-50 w-full max-w-xs translate-x-full shadow-2xl md:translate-x-0'"
                    >
                        <div class="flex items-center justify-between md:hidden">
                            <h2 class="font-serif text-lg font-semibold text-brand-text-primary">Filteri</h2>
                            <button
                                type="button"
                                class="rounded-full p-2 text-brand-text-secondary hover:bg-brand-card hover:text-brand-text-primary"
                                aria-label="Zatvori filtere"
                                @click="showFilters = false"
                            >
                                ✕
                            </button>
                        </div>

                        <div>
                            <label for="f-category" class="block text-sm font-medium text-brand-text-primary">Kategorija</label>
                            <select id="f-category" v-model="form.category" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Sve kategorije</option>
                                <option v-for="c in options.categories" :key="c.id" :value="c.slug">{{ categoryLabel(c) }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-author" class="block text-sm font-medium text-brand-text-primary">Autor</label>
                            <select id="f-author" v-model="form.author" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Svi autori</option>
                                <option v-for="a in options.authors" :key="a.id" :value="a.slug">{{ a.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-publisher" class="block text-sm font-medium text-brand-text-primary">Izdavač</label>
                            <select id="f-publisher" v-model="form.publisher" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Svi izdavači</option>
                                <option v-for="p in options.publishers" :key="p.id" :value="p.slug">{{ p.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-language" class="block text-sm font-medium text-brand-text-primary">Jezik</label>
                            <select id="f-language" v-model="form.language" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Svi jezici</option>
                                <option v-for="l in options.languages" :key="l" :value="l">{{ languageLabel(l) }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-script" class="block text-sm font-medium text-brand-text-primary">Pismo</label>
                            <select id="f-script" v-model="form.script" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Sva pisma</option>
                                <option v-for="s in options.scripts" :key="s" :value="s">{{ scriptLabels[s] ?? s }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="f-format" class="block text-sm font-medium text-brand-text-primary">Format</label>
                            <select id="f-format" v-model="form.format" class="mt-1 block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                <option :value="null">Svi formati</option>
                                <option v-for="f in options.formats" :key="f" :value="f">{{ formatLabels[f] ?? f }}</option>
                            </select>
                        </div>

                        <div>
                            <span class="block text-sm font-medium text-brand-text-primary">Cena (€)</span>
                            <div class="mt-1 flex items-center gap-2">
                                <input v-model="form.price_min" type="number" min="0" step="0.01" placeholder="od" aria-label="Cena od" class="block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                                <span class="text-brand-text-secondary">–</span>
                                <input v-model="form.price_max" type="number" min="0" step="0.01" placeholder="do" aria-label="Cena do" class="block w-full rounded-lg border border-black/10 bg-white text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" />
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-brand-text-primary">
                            <input v-model="form.in_stock" type="checkbox" class="rounded border-black/20 text-brand-accent focus:ring-brand-accent" @change="apply" />
                            Samo na stanju
                        </label>

                        <div v-if="hasActiveFilters" class="flex items-center gap-3 border-t border-black/10 pt-4">
                            <Link :href="route('shop')" class="text-sm text-brand-text-secondary underline hover:text-brand-accent">
                                Poništi filtere
                            </Link>
                        </div>

                        <button
                            type="button"
                            class="mt-2 block w-full rounded-full bg-brand-accent py-2.5 text-sm font-semibold text-white md:hidden"
                            @click="applyAndClose"
                        >
                            Prikaži rezultate
                        </button>
                    </div>
                    </Teleport>

                    <div class="md:col-span-3">
                        <div v-if="books.data.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6">
                            <BookCard v-for="book in books.data" :key="book.slug" :book="book" />
                        </div>
                        <div v-else class="py-16 text-center text-brand-text-secondary">
                            Nema knjiga koje odgovaraju izabranim filterima.
                        </div>

                        <Pagination :links="books.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
