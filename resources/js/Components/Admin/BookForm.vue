<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookCoverPlaceholder from '@/Components/Catalog/BookCoverPlaceholder.vue';

const props = defineProps({
    options: { type: Object, required: true },
    book: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    submitLabel: { type: String, default: 'Sačuvaj knjigu' },
});

const form = useForm({
    category_id: props.book?.category_id ?? '',
    name: props.book?.name ?? '',
    slug: props.book?.slug ?? '',
    description: props.book?.description ?? '',
    price: props.book?.price ?? '',
    stock: props.book?.stock ?? '',
    image: null,
    remove_image: false,
    is_active: props.book?.is_active ?? true,
    isbn: props.book?.isbn ?? '',
    publisher_id: props.book?.publisher_id ?? '',
    subtitle: props.book?.subtitle ?? '',
    original_title: props.book?.original_title ?? '',
    published_year: props.book?.published_year ?? '',
    pages: props.book?.pages ?? '',
    language: props.book?.language ?? 'sr',
    script: props.book?.script ?? '',
    format: props.book?.format ?? 'paperback',
    weight_g: props.book?.weight_g ?? '',
    authors: props.book?.authors ? props.book.authors.map((a) => ({ ...a })) : [],
});

const isEbook = computed(() => form.format === 'ebook');

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

const addAuthor = () => form.authors.push({ author_id: '', role: 'author' });
const removeAuthor = (index) => form.authors.splice(index, 1);

const submit = () => {
    form[props.method](props.submitUrl, { forceFormData: true });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-10">
        <section>
            <h2 class="mb-4 font-serif text-lg font-semibold text-brand-text-primary">Proizvod</h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <InputLabel value="Naslov" />
                    <TextInput v-model="form.name" type="text" required class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel value="Slug (prazno = automatski iz naslova)" />
                    <TextInput v-model="form.slug" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.slug" />
                </div>

                <div>
                    <InputLabel value="Kategorija" />
                    <select
                        v-model="form.category_id"
                        required
                        class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option value="">Izaberi kategoriju</option>
                        <option v-for="category in options.categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.category_id" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel value="Opis" />
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div>
                    <InputLabel value="Cena (€)" />
                    <TextInput v-model="form.price" type="number" step="0.01" min="0" required class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.price" />
                </div>

                <div>
                    <InputLabel>
                        Zaliha<span v-if="isEbook"> (prazno = neograničeno)</span>
                    </InputLabel>
                    <TextInput v-model="form.stock" type="number" min="0" :required="!isEbook" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.stock" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel value="Slika" />

                    <div
                        v-if="book?.image && !currentImageRemoved"
                        class="mt-2 h-32 w-32 overflow-hidden rounded-lg border border-black/10"
                    >
                        <BookCoverPlaceholder :title="book.name" :image="book.image" />
                    </div>

                    <label v-if="book?.image" class="mt-2 flex items-center gap-2">
                        <Checkbox v-model:checked="form.remove_image" />
                        <span class="text-sm text-brand-text-primary">Ukloni trenutnu sliku</span>
                    </label>

                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-2 block w-full text-sm text-brand-text-secondary file:mr-4 file:rounded-md file:border-0 file:bg-brand-accent file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-accent-hover"
                        @change="onImageChange"
                    />
                    <p class="mt-1 text-xs text-brand-text-secondary">
                        JPG, PNG ili WebP, do 2 MB. Ostavi prazno {{ book ? 'da zadržiš postojeću sliku' : 'za placeholder koricu' }}.
                    </p>
                    <InputError class="mt-2" :message="form.errors.image" />
                </div>

                <label class="flex items-center gap-2">
                    <Checkbox v-model:checked="form.is_active" />
                    <span class="text-sm text-brand-text-primary">Aktivna knjiga</span>
                </label>
            </div>
        </section>

        <section>
            <h2 class="mb-4 font-serif text-lg font-semibold text-brand-text-primary">Bibliografski podaci</h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <InputLabel value="ISBN (ISBN-10 ili ISBN-13, crtice su dozvoljene)" />
                    <TextInput v-model="form.isbn" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.isbn" />
                </div>

                <div>
                    <InputLabel value="Izdavač" />
                    <select
                        v-model="form.publisher_id"
                        class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option value="">Bez izdavača</option>
                        <option v-for="publisher in options.publishers" :key="publisher.id" :value="publisher.id">
                            {{ publisher.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.publisher_id" />
                </div>

                <div>
                    <InputLabel value="Podnaslov" />
                    <TextInput v-model="form.subtitle" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.subtitle" />
                </div>

                <div>
                    <InputLabel value="Originalni naslov" />
                    <TextInput v-model="form.original_title" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.original_title" />
                </div>

                <div>
                    <InputLabel value="Godina izdanja" />
                    <TextInput v-model="form.published_year" type="number" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.published_year" />
                </div>

                <div>
                    <InputLabel value="Broj strana" />
                    <TextInput v-model="form.pages" type="number" min="1" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.pages" />
                </div>

                <div>
                    <InputLabel value="Jezik (ISO kod, npr. sr, en)" />
                    <TextInput v-model="form.language" type="text" required maxlength="3" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.language" />
                </div>

                <div>
                    <InputLabel value="Pismo" />
                    <select
                        v-model="form.script"
                        class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option value="">Nije navedeno</option>
                        <option v-for="script in options.scripts" :key="script" :value="script">
                            {{ script === 'Cyrl' ? 'Ćirilica' : 'Latinica' }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.script" />
                </div>

                <div>
                    <InputLabel value="Format" />
                    <select
                        v-model="form.format"
                        required
                        class="mt-1 block w-full rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option v-for="format in options.formats" :key="format" :value="format">{{ format }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.format" />
                </div>

                <div>
                    <InputLabel value="Težina (g)" />
                    <TextInput v-model="form.weight_g" type="number" min="1" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.weight_g" />
                </div>
            </div>
        </section>

        <section>
            <h2 class="mb-4 font-serif text-lg font-semibold text-brand-text-primary">Autori i saradnici</h2>
            <div v-for="(row, index) in form.authors" :key="index" class="mb-3 flex flex-wrap items-start gap-3">
                <div>
                    <select
                        v-model="row.author_id"
                        required
                        class="rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option value="">Izaberi autora</option>
                        <option v-for="author in options.authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                    </select>
                    <div v-if="form.errors[`authors.${index}.author_id`]" class="mt-1 text-sm text-red-600">{{ form.errors[`authors.${index}.author_id`] }}</div>
                </div>
                <div>
                    <select
                        v-model="row.role"
                        required
                        class="rounded-md border-black/20 text-brand-text-primary shadow-sm focus:border-brand-accent focus:ring-brand-accent"
                    >
                        <option v-for="role in options.roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <div v-if="form.errors[`authors.${index}.role`]" class="mt-1 text-sm text-red-600">{{ form.errors[`authors.${index}.role`] }}</div>
                </div>
                <button type="button" @click="removeAuthor(index)" class="mt-1 px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700">
                    Ukloni
                </button>
            </div>
            <div v-if="form.errors.authors" class="mb-2 text-sm text-red-600">{{ form.errors.authors }}</div>
            <button
                type="button"
                @click="addAuthor"
                class="inline-flex items-center rounded-md border border-black/20 bg-white px-3 py-2 text-sm font-medium text-brand-text-primary shadow-sm transition duration-150 ease-in-out hover:bg-brand-card"
            >
                Dodaj autora
            </button>
        </section>

        <div class="flex justify-end gap-4">
            <Link
                :href="route('admin.books.index')"
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
