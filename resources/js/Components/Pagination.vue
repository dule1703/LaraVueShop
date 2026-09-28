<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap gap-1">
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                class="px-3 py-1 rounded border text-sm"
                :class="link.active ? 'bg-brand-accent text-white border-brand-accent' : 'text-brand-text-primary border-black/10 bg-white hover:bg-brand-card'"
                v-html="link.label"
            />
            <span v-else class="px-3 py-1 rounded border border-black/10 text-sm text-brand-text-secondary" v-html="link.label" />
        </template>
    </nav>
</template>
