<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    publisher: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
});

const form = useForm({
    name: props.publisher?.name ?? '',
    slug: props.publisher?.slug ?? '',
    website: props.publisher?.website ?? '',
});

const submit = () => {
    form[props.method](props.submitUrl);
};

const input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700">Naziv</label>
            <input v-model="form.name" type="text" required :class="input" />
            <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Slug (prazno = automatski iz naziva)</label>
            <input v-model="form.slug" type="text" :class="input" />
            <div v-if="form.errors.slug" class="text-red-600 text-sm mt-1">{{ form.errors.slug }}</div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Veb sajt</label>
            <input v-model="form.website" type="url" placeholder="https://..." :class="input" />
            <div v-if="form.errors.website" class="text-red-600 text-sm mt-1">{{ form.errors.website }}</div>
        </div>

        <div class="md:col-span-2 flex justify-end gap-4">
            <Link :href="route('admin.publishers.index')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Otkaži
            </Link>
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                Sačuvaj
            </button>
        </div>
    </form>
</template>
