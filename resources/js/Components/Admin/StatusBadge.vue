<script setup>
defineProps({
    active: {
        type: Boolean,
        required: true,
    },
    activeLabel: {
        type: String,
        default: 'Aktivno',
    },
    inactiveLabel: {
        type: String,
        default: 'Neaktivno',
    },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="active ? 'bg-green-100 text-green-700' : 'bg-black/5 text-brand-text-secondary'"
    >
        {{ active ? activeLabel : inactiveLabel }}
    </span>
</template>
