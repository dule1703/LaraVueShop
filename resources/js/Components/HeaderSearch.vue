<script setup>
import { ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    inputClass: { type: String, default: '' },
    iconClass: { type: String, default: '' },
});

const emit = defineEmits(['submitted']);

const page = usePage();

// URL (query string) je jedini izvor istine, ne lokalni state koji bi se
// mogao raspasinhronizovati sa Shop.vue-ovim sopstvenim search poljem.
// `page.url` (Inertia) sadrži trenutnu putanju + query string i menja se na
// SVAKOJ Inertia navigaciji (uključujući Shop.vue-ov debounce preko
// `replace: true`), pa `watch` ispod drži ovo polje usklađeno čak i kad se
// URL promeni negde drugde (npr. kucanje u Shop.vue-ovom polju).
function searchParamFromUrl() {
    const qIndex = page.url.indexOf('?');
    if (qIndex === -1) return '';
    return new URLSearchParams(page.url.slice(qIndex + 1)).get('search') ?? '';
}

const search = ref(searchParamFromUrl());

watch(() => page.url, () => {
    search.value = searchParamFromUrl();
});

// Ostali filteri (kategorija, cena, itd.) se čuvaju SAMO kad se pretraga radi
// sa /shop ili / (isti katalog) — na bilo kojoj drugoj stranici (Cart,
// Product, ...) query string te stranice nije relevantan za katalog filtere,
// pa se ne prenosi.
function submit() {
    const onCatalog = route().current('shop') || route().current('home');
    const qIndex = page.url.indexOf('?');
    const params = onCatalog && qIndex !== -1
        ? new URLSearchParams(page.url.slice(qIndex + 1))
        : new URLSearchParams();

    const value = search.value.trim();
    if (value) {
        params.set('search', value);
    } else {
        params.delete('search');
    }
    params.delete('page'); // nova pretraga uvek vraća na prvu stranu (isti obrazac kao Shop.vue apply())

    router.get(route('shop'), Object.fromEntries(params), { preserveState: true, preserveScroll: true });
    emit('submitted');
}

// X dugme uvek prazni polje; navigaciju (uklanjanje ?search= iz URL-a, isti
// router.get() obrazac kao submit()) pokreće SAMO kad je search bio stvarno
// aktivan filter (URL trenutno ima ?search=...) - ako je korisnik samo
// otkucao nešto i nikad ga nije poslao (Enter), nema šta da se navigira,
// samo se lokalno polje prazni.
function clear() {
    search.value = '';
    if (searchParamFromUrl()) {
        submit();
    }
}
</script>

<template>
    <div class="relative w-full">
        <input
            v-model="search"
            type="search"
            enterkeyhint="search"
            placeholder="Pretraži knjige, autore..."
            aria-label="Pretraga"
            :class="inputClass"
            @keyup.enter="submit"
        />
        <font-awesome-icon :icon="['fas', 'search']" :class="iconClass" />
        <button
            v-if="search"
            type="button"
            aria-label="Obriši pretragu"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-brand-header-muted transition hover:text-brand-accent"
            @click="clear"
        >
            ×
        </button>
    </div>
</template>
