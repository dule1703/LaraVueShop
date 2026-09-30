<script setup>
import { ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';

const props = defineProps({
    author: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    submitLabel: { type: String, default: 'Sačuvaj autora' },
});

const form = useForm({
    name: props.author?.name ?? '',
    slug: props.author?.slug ?? '',
    bio: props.author?.bio ?? '',
    photo: null,
    remove_photo: false,
});

// Isti obrazac kao BookForm.vue (Faza "Admin panel", korak 2) — trenutna
// slika ostaje prikazana dok admin ne izabere novu ili ne označi uklanjanje.
const currentPhotoRemoved = ref(false);

const onPhotoChange = (event) => {
    form.photo = event.target.files[0] ?? null;
    if (form.photo) {
        form.remove_photo = false;
    }
};

watch(() => form.remove_photo, (removed) => {
    currentPhotoRemoved.value = removed;
    if (removed) {
        form.photo = null;
    }
});

const submit = () => {
    form[props.method](props.submitUrl, { forceFormData: true });
};

const input = 'mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent';
</script>

<template>
    <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <InputLabel value="Ime" />
            <TextInput v-model="form.name" type="text" required class="mt-1 block w-full" />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel value="Slug (prazno = automatski iz imena)" />
            <TextInput v-model="form.slug" type="text" class="mt-1 block w-full" />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>

        <div class="md:col-span-2">
            <InputLabel value="Biografija" />
            <textarea v-model="form.bio" rows="4" :class="input"></textarea>
            <InputError class="mt-2" :message="form.errors.bio" />
        </div>

        <div class="md:col-span-2">
            <InputLabel value="Fotografija" />

            <div
                v-if="author?.photo && !currentPhotoRemoved"
                class="mt-2 h-32 w-32 overflow-hidden rounded-lg border border-black/10"
            >
                <BookCoverPlaceholder :title="author.name" :image="author.photo" />
            </div>

            <label v-if="author?.photo" class="mt-2 flex items-center gap-2">
                <Checkbox v-model:checked="form.remove_photo" />
                <span class="text-sm text-brand-text-primary">Ukloni trenutnu fotografiju</span>
            </label>

            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-2 block w-full text-sm text-brand-text-secondary file:mr-4 file:rounded-md file:border-0 file:bg-brand-accent file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-accent-hover"
                @change="onPhotoChange"
            />
            <p class="mt-1 text-xs text-brand-text-secondary">
                JPG, PNG ili WebP, do 2 MB. Ostavi prazno {{ author ? 'da zadržiš postojeću fotografiju' : 'za placeholder' }}.
            </p>
            <InputError class="mt-2" :message="form.errors.photo" />
        </div>

        <div class="md:col-span-2 flex justify-end gap-4">
            <Link
                :href="route('admin.authors.index')"
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
