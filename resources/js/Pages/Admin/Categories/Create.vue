<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    is_active: true,
});
</script>

<template>
    <Head title="Dodaj kategoriju" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-brand-page py-8 md:py-12">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <AdminPageHeader title="Dodaj kategoriju" />

                <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-sm">
                    <form @submit.prevent="form.post(route('admin.categories.store'))">
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <InputLabel for="name" value="Naziv" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="description" value="Opis (opciono)" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <label class="flex items-center gap-2">
                                <Checkbox v-model:checked="form.is_active" />
                                <span class="text-sm text-brand-text-primary">Aktivna kategorija</span>
                            </label>

                            <div class="flex justify-end gap-4">
                                <Link
                                    :href="route('admin.categories.index')"
                                    class="inline-flex items-center rounded-md border border-black/20 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-brand-text-primary shadow-sm transition duration-150 ease-in-out hover:bg-brand-card"
                                >
                                    Otkaži
                                </Link>
                                <PrimaryButton :disabled="form.processing">
                                    Sačuvaj kategoriju
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
