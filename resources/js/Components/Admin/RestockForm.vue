<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    bookId: {
        type: [Number, String],
        required: true,
    },
    bookName: {
        type: String,
        required: true,
    },
    restockUrl: {
        type: String,
        required: true,
    },
});

const open = ref(false);
const quantity = ref(1);
const note = ref('');
const processing = ref(false);

const submit = () => {
    processing.value = true;

    router.post(props.restockUrl, {
        quantity: quantity.value,
        note: note.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            open.value = false;
            quantity.value = 1;
            note.value = '';
        },
    });
};
</script>

<template>
    <button @click="open = true" class="text-emerald-600 hover:text-emerald-900">
        Dopuni
    </button>

    <div v-if="open" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" @click="open = false"></div>

    <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="w-full max-w-md transform overflow-hidden rounded-lg bg-white p-6 text-left align-middle shadow-xl transition-all">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                    Dopuna zaliha
                </h3>
                <p class="mt-1 text-sm text-gray-500">{{ bookName }}</p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Količina</label>
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Napomena (opciono)</label>
                    <input
                        v-model="note"
                        type="text"
                        maxlength="255"
                        placeholder="npr. nova pošiljka od izdavača"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="open = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Otkaži
                    </button>
                    <button
                        @click="submit"
                        :disabled="processing || !quantity || quantity < 1"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 disabled:opacity-50"
                    >
                        Sačuvaj
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
