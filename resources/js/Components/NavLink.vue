<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
    // 'base' je namerno opt-in (ne default) - samo Shop link u headeru ga
    // koristi da se istakne pored logotipa; admin nav (6 linkova) ostaje na
    // 'sm' preko default vrednosti. Menja se OVDE, unutar istog computed()
    // bloka, umesto kroz spoljni `class` override - Tailwind-ov generisani
    // CSS ne garantuje redosled klasa iz template-a, pa bi spoljni
    // `text-base` mogao tiho izgubiti od unutrašnjeg `text-sm`.
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'base'].includes(value),
    },
});

const textSize = computed(() => (props.size === 'base' ? 'text-base' : 'text-sm'));

const classes = computed(() =>
    props.active
        ? `inline-flex items-center px-1 pt-1 border-b-2 border-brand-accent ${textSize.value} font-medium leading-5 text-brand-header-text focus:outline-none transition duration-150 ease-in-out`
        : `inline-flex items-center px-1 pt-1 border-b-2 border-transparent ${textSize.value} font-medium leading-5 text-brand-header-muted hover:text-brand-header-text hover:border-brand-header-muted focus:outline-none transition duration-150 ease-in-out`,
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
