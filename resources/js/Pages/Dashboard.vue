<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stats: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? '');
</script>

<template>
    <Head title="Početna" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <template v-if="stats">
                    <h1 class="mb-6 font-serif text-2xl font-semibold text-brand-text-primary">
                        Pregled
                    </h1>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">
                                Ukupno porudžbina
                            </p>
                            <p class="mt-2 font-serif text-3xl font-semibold text-brand-text-primary">
                                {{ stats.total_orders }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">
                                Porudžbine na čekanju
                            </p>
                            <p class="mt-2 font-serif text-3xl font-semibold text-brand-text-primary">
                                {{ stats.pending_orders }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">
                                Ukupno proizvoda
                            </p>
                            <p class="mt-2 font-serif text-3xl font-semibold text-brand-text-primary">
                                {{ stats.total_products }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border p-6 shadow-sm"
                            :class="stats.low_stock_products > 0 ? 'border-amber-300 bg-amber-50' : 'border-black/5 bg-white'"
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide"
                                :class="stats.low_stock_products > 0 ? 'text-amber-700' : 'text-brand-text-secondary'"
                            >
                                Niska zaliha
                            </p>
                            <p
                                class="mt-2 font-serif text-3xl font-semibold"
                                :class="stats.low_stock_products > 0 ? 'text-amber-700' : 'text-brand-text-primary'"
                            >
                                {{ stats.low_stock_products }}
                            </p>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="rounded-2xl border border-black/5 bg-white p-10 text-center shadow-sm">
                        <h1 class="mb-2 font-serif text-2xl font-semibold text-brand-text-primary">
                            Dobrodošli{{ userName ? ', ' + userName : '' }}!
                        </h1>
                        <p class="text-brand-text-secondary">Uspešno ste ulogovani.</p>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
