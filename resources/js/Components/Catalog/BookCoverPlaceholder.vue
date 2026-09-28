<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    author: { type: String, default: '' },
    image: { type: String, default: null },
});

// Topla, "vintage korica" paleta — deterministički izabrana iz naslova, tako
// da ista knjiga uvek dobija istu boju, a susedne kartice u gridu variraju.
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
            <p v-if="author" class="text-xs text-brand-text-secondary line-clamp-1 sm:text-sm">
                {{ author }}
            </p>
        </div>
    </div>
</template>
