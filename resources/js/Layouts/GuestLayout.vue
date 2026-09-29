<script setup>
import Logo from '@/Components/Logo.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-brand-header pt-6 sm:justify-center sm:pt-0"
    >
        <Link
            href="/shop"
            class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-brand-header-text hover:text-brand-accent"
        >
            <ArrowLeft :size="16" />
            Nazad u prodavnicu
        </Link>

        <Link href="/" class="text-4xl text-brand-header-text">
            <Logo />
        </Link>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
