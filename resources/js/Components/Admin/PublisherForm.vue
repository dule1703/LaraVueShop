<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    publisher: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    submitLabel: { type: String, default: 'Sačuvaj izdavača' },
});

const form = useForm({
    name: props.publisher?.name ?? '',
    slug: props.publisher?.slug ?? '',
    website: props.publisher?.website ?? '',
});

const submit = () => {
    form[props.method](props.submitUrl);
};
</script>

<template>
    <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <InputLabel value="Naziv" />
            <TextInput v-model="form.name" type="text" required class="mt-1 block w-full" />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel value="Slug (prazno = automatski iz naziva)" />
            <TextInput v-model="form.slug" type="text" class="mt-1 block w-full" />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>

        <div class="md:col-span-2">
            <InputLabel value="Veb sajt" />
            <TextInput v-model="form.website" type="url" placeholder="https://..." class="mt-1 block w-full" />
            <InputError class="mt-2" :message="form.errors.website" />
        </div>

        <div class="md:col-span-2 flex justify-end gap-4">
            <Link
                :href="route('admin.publishers.index')"
                class="inline-flex items-center rounded-md border border-black/20 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-brand-text-primary shadow-sm transition duration-150 ease-in-out hover:bg-brand-card"
            >
                Otkaži
            </Link>
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
