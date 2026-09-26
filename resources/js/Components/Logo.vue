<script setup>
import { BookOpen } from 'lucide-vue-next';

defineProps({
    color: {
        type: String,
        default: 'currentColor',
    },
});
</script>

<template>
    <span class="inline-flex items-center gap-2" :style="{ color }">
        <BookOpen size="1.4em" :stroke-width="1.75" class="shrink-0" />
        <span class="font-serif tracking-wide">Ex Libris</span>
    </span>
</template>
