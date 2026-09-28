<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCard from '@/Components/Catalog/BookCard.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { formatLabels, formatPrice, languageLabel, scriptLabels } from '@/lib/bookLabels';

const props = defineProps({
    books: Object,
    filters: Object,
    options: Object,
});

const PRICE_DEBOUNCE_MS = 400;

// `search` NIJE deo `form`-a — živi isključivo u HeaderSearch.vue/URL-u
// (jedan izvor istine, vidi `apply()`/`buildParams()` ispod). Bez ovoga bi
// se `props.filters.search` (bilo koji string) kopiralo u `form.search`
// pri mount-u i onda zauvek ostalo "zamrznuto" na toj vrednosti u `apply()`
// pozivima — arhitektonski uzrok bug-a iz fix/search-sync PR-a.
const { search: _initialSearch, ...initialFormFilters } = props.filters;
const form = reactive(initialFormFilters);
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

// Back/forward i "Poništi sve" (Link na route('shop')) menjaju props bez
// remount-a komponente. `syncingFromProps` sprečava da ovo sinhronizovanje
// samo sebe okine kao da je korisnik otkucao nešto u polju (vidi debounce
// watch ispod, sad samo za price_min/price_max — `search` više NIJE u
// `form`-u, pa ga ovaj watch ne mora štititi) — bez toga bi svaka promena
// cene (odakle god) zakazala dodatni, potpuno redundantan apply() 400ms
// kasnije. `await nextTick()` drži flag true dok se debounce watch (isti
// flush ciklus, `flush: 'pre'` podrazumevano) stvarno ne izvrši —
// resetovanje flag-a odmah posle `Object.assign` (sinhrono) NE bi radilo jer
// se watcher-i ne izvršavaju sinhrono unutar iste linije koda.
let syncingFromProps = false;
watch(() => props.filters, async (filters) => {
    syncingFromProps = true;
    const { search: _search, ...rest } = filters;
    Object.assign(form, rest);
    await nextTick();
    syncingFromProps = false;
});

watch([() => form.price_min, () => form.price_max], () => {
    if (syncingFromProps) return;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(apply, PRICE_DEBOUNCE_MS);
});

const hasActiveFilters = computed(() => Object.values(props.filters).some((v) => v !== null && v !== false));

// I dalje potreban i posle uklanjanja `form.search` (DEO A): `apply()` čita
// `props.filters.search` SVEŽE u trenutku poziva (vidi `buildParams()`
// ispod) — ali ako je TADA neka DRUGA Inertia navigacija (npr.
// HeaderSearch.vue-ov router.get(), ili klik na paginaciju) još u letu,
// `props.filters.search` može I DALJE biti zastareo (ta navigacija još nije
// stigla da ažurira props). Uklanjanje `form.search` premešta GDE se
// zastarelost mogla dogoditi, ne UKLANJA je — trka je suštinski o DVE
// KONKURENTNE Inertia navigacije, ne o tome gde je `search` sačuvan. Bez ove
// zaštite, lokalna promena filtera bi i dalje mogla poslati zastareo/prazan
// search i tiho pregaziti pretragu iz header-a (potvrđeno pre ovog PR-a,
// pre DEO A izmene, direktnom reprodukcijom). `router.on('start'/'finish',
// ...)` su GLOBALNI Inertia event-ovi (okidaju se za SVAKU navigaciju, ne
// samo one pokrenute iz ovog fajla). Kad se prijavi da je navigacija u toku,
// `apply()` samo zakaže `pendingReapply` i vrati se bez slanja zahteva; čim
// se navigacija završi (i `watch(props.filters)` iznad sinhronizuje `form`,
// a `props.filters.search` je već sveže od strane Inertia-e), `apply()` se
// automatski ponovo pozove sa ISPRAVNO spojenim filterima.
let navigationInFlight = false;
let pendingReapply = false;

const stopNavigationStart = router.on('start', () => {
    navigationInFlight = true;
});
const stopNavigationFinish = router.on('finish', () => {
    navigationInFlight = false;
    if (pendingReapply) {
        pendingReapply = false;
        apply();
    }
});

onUnmounted(() => {
    stopNavigationStart();
    stopNavigationFinish();
});

// `excludeSearch` — koristi ga samo `removeFilter('search')` (klik na × na
// search chip-u) da eksplicitno IZOSTAVI search iz sledećeg zahteva, umesto
// da ga (kao inače) ponovo doda iz `props.filters.search`.
function buildParams(excludeSearch = false) {
    const params = {};
    for (const [key, value] of Object.entries(form)) {
        if (value === null || value === '' || value === false) continue;
        params[key] = value === true ? 1 : value;
    }
    if (!excludeSearch && props.filters.search) {
        params.search = props.filters.search;
    }
    return params;
}

function apply() {
    clearTimeout(debounceTimer);

    if (navigationInFlight) {
        pendingReapply = true;
        return;
    }

    // Bez `page` — svaka promena filtera vraća na prvu stranu.
    router.get(route('shop'), buildParams(), { preserveState: true, preserveScroll: true, replace: true });
}

function applyAndClose() {
    apply();
    showFilters.value = false;
}

function categoryLabel(category) {
    return String.fromCharCode(160).repeat(category.depth * 2) + category.name;
}

// Labele za chip-ove aktivnih filtera (DEO B) — čita direktno iz
// `props.filters` (isti izvor kao `hasActiveFilters`), ne iz `form`-a, da
// chip-ovi UVEK odražavaju stvarno stanje sa servera/URL-a.
const chipLabel = {
    category: (v) => props.options.categories.find((c) => c.slug === v)?.name ?? v,
    author: (v) => props.options.authors.find((a) => a.slug === v)?.name ?? v,
    publisher: (v) => props.options.publishers.find((p) => p.slug === v)?.name ?? v,
    language: (v) => languageLabel(v),
    script: (v) => scriptLabels[v] ?? v,
    format: (v) => formatLabels[v] ?? v,
    price_min: (v) => `od ${formatPrice(v)}`,
    price_max: (v) => `do ${formatPrice(v)}`,
    in_stock: () => 'Samo na stanju',
    search: (v) => `Pretraga: ${v}`,
};

const activeChips = computed(() => Object.entries(props.filters)
    .filter(([, value]) => value !== null && value !== false)
    .map(([key, value]) => ({ key, label: chipLabel[key]?.(value) ?? String(value) })));

function removeFilter(key) {
    if (key === 'search') {
        router.get(route('shop'), buildParams(true), { preserveState: true, preserveScroll: true, replace: true });
        return;
    }
    form[key] = key === 'in_stock' ? false : null;
    apply();
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
    // Pre-postojeći propust (nije uveden ovim PR-om, ali otkriven pri reviziji
    // istog fajla): bez ovoga bi debounceTimer i dalje pozvao apply() posle
    // unmount-a (npr. korisnik otkuca cenu pa odmah klikne na knjigu pre
    // isteka 400ms) - router.get('shop', ...) bi tad tiho prekinuo navigaciju
    // na koju je korisnik već otišao.
    clearTimeout(debounceTimer);
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

                    <!-- Mobilni drawer: puna visina, klizi sa desne strane (bez kartice —
                         drawer je već sopstveni kontejner). Desktop (md+): bg-brand-card
                         kartica, sticky ispod nav trake — isti unutrašnji dizajn (grupe,
                         labele, select-i) dele obe varijante, samo se spoljni "omotač"
                         razlikuje. -->
                    <div
                        :role="isDesktopFilters ? undefined : 'dialog'"
                        :aria-modal="isDesktopFilters ? undefined : 'true'"
                        aria-label="Filteri"
                        class="space-y-6 overflow-y-auto bg-brand-page p-5 transition-transform duration-300 ease-out md:visible md:static md:z-auto md:h-auto md:w-auto md:translate-x-0 md:rounded-2xl md:bg-brand-card md:p-5 md:shadow-none md:transition-none md:sticky md:top-24"
                        :class="showFilters
                            ? 'fixed inset-y-0 right-0 z-50 w-full max-w-xs translate-x-0 shadow-2xl'
                            : 'invisible fixed inset-y-0 right-0 z-50 w-full max-w-xs translate-x-full shadow-2xl md:translate-x-0'"
                    >
                        <div class="flex items-center justify-between">
                            <h2 class="font-serif text-lg font-semibold text-brand-text-primary">Filteri</h2>
                            <button
                                type="button"
                                class="rounded-full p-2 text-brand-text-secondary hover:bg-brand-card hover:text-brand-text-primary md:hidden"
                                aria-label="Zatvori filtere"
                                @click="showFilters = false"
                            >
                                ✕
                            </button>
                        </div>

                        <div>
                            <label for="f-category" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Kategorija</label>
                            <div class="relative mt-1.5">
                                <select id="f-category" v-model="form.category" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Sve kategorije</option>
                                    <option v-for="c in options.categories" :key="c.id" :value="c.slug">{{ categoryLabel(c) }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div class="border-t border-black/10 pt-5">
                            <label for="f-author" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Autor</label>
                            <div class="relative mt-1.5">
                                <select id="f-author" v-model="form.author" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Svi autori</option>
                                    <option v-for="a in options.authors" :key="a.id" :value="a.slug">{{ a.name }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div>
                            <label for="f-publisher" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Izdavač</label>
                            <div class="relative mt-1.5">
                                <select id="f-publisher" v-model="form.publisher" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Svi izdavači</option>
                                    <option v-for="p in options.publishers" :key="p.id" :value="p.slug">{{ p.name }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div class="border-t border-black/10 pt-5">
                            <label for="f-language" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Jezik</label>
                            <div class="relative mt-1.5">
                                <select id="f-language" v-model="form.language" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Svi jezici</option>
                                    <option v-for="l in options.languages" :key="l" :value="l">{{ languageLabel(l) }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div>
                            <label for="f-script" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Pismo</label>
                            <div class="relative mt-1.5">
                                <select id="f-script" v-model="form.script" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Sva pisma</option>
                                    <option v-for="s in options.scripts" :key="s" :value="s">{{ scriptLabels[s] ?? s }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div class="border-t border-black/10 pt-5">
                            <label for="f-format" class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Format</label>
                            <div class="relative mt-1.5">
                                <select id="f-format" v-model="form.format" class="block w-full appearance-none rounded-lg border border-black/10 bg-white py-2 pl-3 pr-9 text-sm text-brand-text-primary focus:border-brand-accent focus:ring-brand-accent" @change="apply">
                                    <option :value="null">Svi formati</option>
                                    <option v-for="f in options.formats" :key="f" :value="f">{{ formatLabels[f] ?? f }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-accent" />
                            </div>
                        </div>

                        <div class="border-t border-black/10 pt-5">
                            <span class="block text-xs font-medium uppercase tracking-wide text-brand-text-secondary">Cena</span>
                            <div class="mt-1.5 flex items-center gap-2 rounded-lg border border-black/10 bg-white px-3 py-2 focus-within:border-brand-accent focus-within:ring-2 focus-within:ring-brand-accent">
                                <input v-model="form.price_min" type="number" min="0" step="0.01" placeholder="od" aria-label="Cena od" class="w-full border-0 p-0 text-sm text-brand-text-primary focus:ring-0" />
                                <span class="text-brand-text-secondary">–</span>
                                <input v-model="form.price_max" type="number" min="0" step="0.01" placeholder="do" aria-label="Cena do" class="w-full border-0 p-0 text-sm text-brand-text-primary focus:ring-0" />
                                <span class="shrink-0 text-sm text-brand-text-secondary">€</span>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 border-t border-black/10 pt-5 text-sm text-brand-text-primary">
                            <input v-model="form.in_stock" type="checkbox" class="form-checkbox h-4 w-4 rounded border-black/20 text-brand-accent focus:ring-brand-accent" @change="apply" />
                            Samo na stanju
                        </label>

                        <button
                            type="button"
                            class="block w-full rounded-full bg-brand-accent py-2.5 text-sm font-semibold text-white md:hidden"
                            @click="applyAndClose"
                        >
                            Prikaži rezultate
                        </button>
                    </div>
                    </Teleport>

                    <div class="md:col-span-3">
                        <!-- Aktivni filteri kao chip-ovi (DEO B) — iznad rezultata, uključujući
                             search (jedini prikaz aktivne pretrage otkad je Shop.vue-ovo
                             sopstveno search polje uklonjeno, vidi DEO A/header search). -->
                        <div v-if="activeChips.length" class="mb-4 flex flex-wrap items-center gap-2">
                            <span
                                v-for="chip in activeChips"
                                :key="chip.key"
                                class="inline-flex items-center gap-1.5 rounded-full bg-brand-card px-3 py-1 text-xs font-medium text-brand-text-primary"
                            >
                                {{ chip.label }}
                                <button
                                    type="button"
                                    class="text-brand-text-secondary transition hover:text-brand-accent"
                                    :aria-label="`Ukloni filter: ${chip.label}`"
                                    @click="removeFilter(chip.key)"
                                >
                                    ×
                                </button>
                            </span>
                            <Link
                                :href="route('shop')"
                                class="ml-1 rounded-full border border-black/10 px-3 py-1 text-xs font-medium text-brand-text-secondary transition hover:border-brand-accent hover:text-brand-accent"
                            >
                                Poništi sve
                            </Link>
                        </div>

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
