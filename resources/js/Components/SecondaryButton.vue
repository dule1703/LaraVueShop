<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-black/20 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-brand-text-primary shadow-sm transition duration-150 ease-in-out hover:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-accent focus:ring-offset-2 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
