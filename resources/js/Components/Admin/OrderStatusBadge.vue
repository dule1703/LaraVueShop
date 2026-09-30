<script setup>
import { orderStatusLabels, orderStatusStyles } from '@/lib/orderLabels';

defineProps({
    status: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="orderStatusStyles[status] ?? 'bg-black/5 text-brand-text-secondary'"
    >
        {{ orderStatusLabels[status] ?? status }}
    </span>
</template>
