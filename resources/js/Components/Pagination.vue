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
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-700 border-gray-300 hover:bg-gray-50'"
                v-html="link.label"
            />
            <span v-else class="px-3 py-1 rounded border border-gray-200 text-sm text-gray-400" v-html="link.label" />
        </template>
    </nav>
</template>
