<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatPrice } from '@/lib/bookLabels';

defineProps({
    order: Object,
});

const page = usePage();
const reason = computed(() => page.props.flash?.error);
</script>

<template>
    <Head title="Plaćanje nije uspelo" />

    <AuthenticatedLayout>
        <div class="bg-brand-page py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="p-10 text-center">
                        <XCircle class="mx-auto mb-6 h-20 w-20 text-red-600" :stroke-width="1.5" />
                        <h1 class="mb-4 font-serif text-3xl font-semibold text-brand-text-primary">
                            Plaćanje nije uspelo
                        </h1>

                        <p class="mb-6 text-lg text-brand-text-secondary">
                            Nažalost, plaćanje za porudžbinu #{{ order.id }} nije moglo biti završeno.
                        </p>

                        <div class="mb-8 text-base text-brand-text-primary">
                            <p>Iznos: <strong>{{ formatPrice(order.total_price) }}</strong></p>
                            <p class="mt-1">
                                Status:
                                <strong class="text-red-600">{{ order.status }}</strong>
                            </p>
                        </div>

                        <div
                            v-if="reason"
                            class="mx-auto mb-8 max-w-xl rounded-lg border border-red-200 bg-red-50 p-4 text-left text-sm text-red-700"
                        >
                            <strong>Razlog:</strong> {{ reason }}
                        </div>

                        <div class="flex flex-col justify-center gap-4 sm:flex-row">
                            <a
                                href="/checkout"
                                class="inline-block rounded-lg bg-brand-accent px-8 py-3 text-lg font-medium text-white transition hover:bg-brand-accent-hover"
                            >
                                Pokušaj ponovo
                            </a>
                            <a
                                href="/shop"
                                class="inline-block rounded-lg border border-black/20 bg-white px-8 py-3 text-lg font-medium text-brand-text-primary transition hover:bg-brand-card"
                            >
                                Nazad u prodavnicu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
