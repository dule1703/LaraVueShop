<script setup>
// Deljena tabela za admin listing stranice (Faza "Admin panel", korak 1 —
// pattern uspostavljen na Categories, ponovo se koristi u Products/Books,
// Authors/Publishers, Orders). Pozivalac isporučuje `<tr>` redove kroz
// default slot; svaka ćelija treba `class="px-6 py-4"` (whitespace-nowrap
// po potrebi) radi konzistentnog razmaka sa header ćelijama ovde.
defineProps({
    headers: {
        type: Array,
        required: true,
    },
    isEmpty: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'Nema podataka.',
    },
});
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/5">
                <thead class="bg-brand-card">
                    <tr>
                        <th
                            v-for="header in headers"
                            :key="header"
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-text-secondary"
                        >
                            {{ header }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 bg-white">
                    <slot />
                </tbody>
            </table>
        </div>

        <p v-if="isEmpty" class="py-10 text-center text-sm text-brand-text-secondary">
            {{ emptyMessage }}
        </p>
    </div>
</template>
