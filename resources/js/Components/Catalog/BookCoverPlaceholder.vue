<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    author: { type: String, default: '' },
    image: { type: String, default: null },
});

// Topla, "vintage korica" paleta — deterministički izabrana iz naslova, tako
// da ista knjiga uvek dobija istu boju, a susedne kartice u gridu variraju.
// Autor je namerno text-brand-text-primary (ista boja kao naslov, razlikuje
// se kurzivom/veličinom, ne bojom): izračunat WCAG kontrast
// text-brand-text-secondary (#8A7461, ranija boja) protiv svih 6 nijansi
// daje 2.08:1-3.84:1 — nijedna ne dostiže AA 4.5:1, čak ni najsvetlija.
// brand-header-text (#6B4423) prolazi na 5 od 6, ali pada na 3.98:1 na
// najtamnijoj (#C9AD8F). brand-text-primary (#2E241C) prolazi 7.1:1-13.2:1
// na svih 6 — izabran umesto brisanja dve najtamnije nijanse da paleta
// ostane puna (šira vizuelna varijacija u gridu).
const PALETTE = ['#F6EEE3', '#EAD9C5', '#E4CBAE', '#D9C2A6', '#C9AD8F', '#DDCFC0'];

function hashString(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = (hash * 31 + str.charCodeAt(i)) | 0;
    }
    return Math.abs(hash);
}

const backgroundColor = computed(() => PALETTE[hashString(props.title) % PALETTE.length]);
</script>

<template>
    <div class="relative h-full w-full overflow-hidden">
        <img
            v-if="image"
            :src="image"
            :alt="title"
            loading="lazy"
            class="h-full w-full object-cover"
        />
        <div
            v-else
            class="flex h-full w-full flex-col items-center justify-center gap-2 p-4 text-center"
            :style="{ backgroundColor }"
        >
            <div class="pointer-events-none absolute inset-2 rounded-sm border border-white/40"></div>
            <p class="font-serif text-base font-semibold leading-snug text-brand-text-primary line-clamp-4 sm:text-lg">
                {{ title }}
            </p>
            <p v-if="author" class="text-xs italic text-brand-text-primary line-clamp-1 sm:text-sm">
                {{ author }}
            </p>
        </div>
    </div>
</template>
