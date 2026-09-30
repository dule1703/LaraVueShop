<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-serif text-2xl font-semibold text-brand-text-primary">{{ title }}</h1>
        <div class="flex items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>
