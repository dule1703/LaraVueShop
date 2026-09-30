<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    product: Object,
    categories: Array
});

const form = useForm({
    category_id: props.product.category_id,
    name: props.product.name,
    description: props.product.description || '',
    price: props.product.price,
    stock: props.product.stock,
    image: null,
    remove_image: false,
    is_active: props.product.is_active,
});

// Trenutna slika ostaje prikazana dok admin ne izabere novu ili ne označi
// uklanjanje — <input type="file"> se ne može unapred popuniti postojećim
// URL-om (HTML ograničenje), pa je ovo zaseban lokalni prikaz, ne deo forme.
const currentImageRemoved = ref(false);

const onImageChange = (event) => {
    form.image = event.target.files[0] ?? null;
    if (form.image) {
        form.remove_image = false;
    }
};

watch(() => form.remove_image, (removed) => {
    currentImageRemoved.value = removed;
    if (removed) {
        form.image = null;
    }
});
</script>

<template>
    <Head title="Izmeni proizvod" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Izmeni proizvod" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <form @submit.prevent="form.put(route('admin.products.update', product.id), { forceFormData: true })">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <InputLabel for="category_id" value="Kategorija" />
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    required
                                    class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                                >
                                    <option value="">Izaberi kategoriju</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.category_id" />
                            </div>

                            <div>
                                <InputLabel for="name" value="Naziv" />
                                <TextInput id="name" v-model="form.name" type="text" required class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="description" value="Opis" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div>
                                <InputLabel for="price" value="Cena (€)" />
                                <TextInput id="price" v-model="form.price" type="number" step="0.01" required class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.price" />
                            </div>

                            <div>
                                <InputLabel for="stock" value="Zaliha" />
                                <TextInput id="stock" v-model="form.stock" type="number" required class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.stock" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel value="Slika" />

                                <div
                                    v-if="product.image && !currentImageRemoved"
                                    class="mt-2 h-32 w-32 overflow-hidden rounded-lg border border-black/10"
                                >
                                    <BookCoverPlaceholder :title="product.name" :image="product.image" />
                                </div>

                                <label v-if="product.image" class="mt-2 flex items-center gap-2">
                                    <Checkbox v-model:checked="form.remove_image" />
                                    <span class="text-sm text-brand-text-primary">Ukloni trenutnu sliku</span>
                                </label>

                                <input
                                    id="image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="mt-2 block w-full text-sm text-brand-text-secondary file:mr-4 file:rounded-md file:border-0 file:bg-brand-accent file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-accent-hover"
                                    @change="onImageChange"
                                />
                                <p class="mt-1 text-xs text-brand-text-secondary">JPG, PNG ili WebP, do 2 MB. Ostavi prazno da zadržiš postojeću sliku.</p>
                                <InputError class="mt-2" :message="form.errors.image" />
                            </div>

                            <label class="flex items-center gap-2">
                                <Checkbox v-model:checked="form.is_active" />
                                <span class="text-sm text-brand-text-primary">Aktivan proizvod</span>
                            </label>
                        </div>

                        <div class="mt-8 flex justify-end gap-4">
                            <Link
                                :href="route('admin.products.index')"
                                class="inline-flex items-center rounded-md border border-black/20 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-brand-text-primary shadow-sm transition duration-150 ease-in-out hover:bg-brand-card"
                            >
                                Otkaži
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                Sačuvaj
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
