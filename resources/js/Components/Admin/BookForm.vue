<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

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
    image: props.book?.image ?? '',
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

const addAuthor = () => form.authors.push({ author_id: '', role: 'author' });
const removeAuthor = (index) => form.authors.splice(index, 1);

const submit = () => {
    form[props.method](props.submitUrl);
};

const input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const label = 'block text-sm font-medium text-gray-700';
const error = 'text-red-600 text-sm mt-1';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-10">
        <section>
            <h2 class="text-lg font-semibold mb-4">Proizvod</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label :class="label">Naslov</label>
                    <input v-model="form.name" type="text" required :class="input" />
                    <div v-if="form.errors.name" :class="error">{{ form.errors.name }}</div>
                </div>

                <div>
                    <label :class="label">Slug (prazno = automatski iz naslova)</label>
                    <input v-model="form.slug" type="text" :class="input" />
                    <div v-if="form.errors.slug" :class="error">{{ form.errors.slug }}</div>
                </div>

                <div>
                    <label :class="label">Kategorija</label>
                    <select v-model="form.category_id" required :class="input">
                        <option value="">Izaberi kategoriju</option>
                        <option v-for="category in options.categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.category_id" :class="error">{{ form.errors.category_id }}</div>
                </div>

                <div class="md:col-span-2">
                    <label :class="label">Opis</label>
                    <textarea v-model="form.description" rows="4" :class="input"></textarea>
                    <div v-if="form.errors.description" :class="error">{{ form.errors.description }}</div>
                </div>

                <div>
                    <label :class="label">Cena (€)</label>
                    <input v-model="form.price" type="number" step="0.01" min="0" required :class="input" />
                    <div v-if="form.errors.price" :class="error">{{ form.errors.price }}</div>
                </div>

                <div>
                    <label :class="label">
                        Zaliha<span v-if="isEbook"> (prazno = neograničeno)</span>
                    </label>
                    <input v-model="form.stock" type="number" min="0" :required="!isEbook" :class="input" />
                    <div v-if="form.errors.stock" :class="error">{{ form.errors.stock }}</div>
                </div>

                <div class="md:col-span-2">
                    <label :class="label">URL slike</label>
                    <input v-model="form.image" type="url" placeholder="https://..." :class="input" />
                    <div v-if="form.errors.image" :class="error">{{ form.errors.image }}</div>
                </div>

                <div class="flex items-center">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                    <label class="ml-2 block text-sm text-gray-900">Aktivna knjiga</label>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-4">Bibliografski podaci</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label :class="label">ISBN (ISBN-10 ili ISBN-13, crtice su dozvoljene)</label>
                    <input v-model="form.isbn" type="text" :class="input" />
                    <div v-if="form.errors.isbn" :class="error">{{ form.errors.isbn }}</div>
                </div>

                <div>
                    <label :class="label">Izdavač</label>
                    <select v-model="form.publisher_id" :class="input">
                        <option value="">Bez izdavača</option>
                        <option v-for="publisher in options.publishers" :key="publisher.id" :value="publisher.id">
                            {{ publisher.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.publisher_id" :class="error">{{ form.errors.publisher_id }}</div>
                </div>

                <div>
                    <label :class="label">Podnaslov</label>
                    <input v-model="form.subtitle" type="text" :class="input" />
                    <div v-if="form.errors.subtitle" :class="error">{{ form.errors.subtitle }}</div>
                </div>

                <div>
                    <label :class="label">Originalni naslov</label>
                    <input v-model="form.original_title" type="text" :class="input" />
                    <div v-if="form.errors.original_title" :class="error">{{ form.errors.original_title }}</div>
                </div>

                <div>
                    <label :class="label">Godina izdanja</label>
                    <input v-model="form.published_year" type="number" :class="input" />
                    <div v-if="form.errors.published_year" :class="error">{{ form.errors.published_year }}</div>
                </div>

                <div>
                    <label :class="label">Broj strana</label>
                    <input v-model="form.pages" type="number" min="1" :class="input" />
                    <div v-if="form.errors.pages" :class="error">{{ form.errors.pages }}</div>
                </div>

                <div>
                    <label :class="label">Jezik (ISO kod, npr. sr, en)</label>
                    <input v-model="form.language" type="text" required maxlength="3" :class="input" />
                    <div v-if="form.errors.language" :class="error">{{ form.errors.language }}</div>
                </div>

                <div>
                    <label :class="label">Pismo</label>
                    <select v-model="form.script" :class="input">
                        <option value="">Nije navedeno</option>
                        <option v-for="script in options.scripts" :key="script" :value="script">
                            {{ script === 'Cyrl' ? 'Ćirilica' : 'Latinica' }}
                        </option>
                    </select>
                    <div v-if="form.errors.script" :class="error">{{ form.errors.script }}</div>
                </div>

                <div>
                    <label :class="label">Format</label>
                    <select v-model="form.format" required :class="input">
                        <option v-for="format in options.formats" :key="format" :value="format">{{ format }}</option>
                    </select>
                    <div v-if="form.errors.format" :class="error">{{ form.errors.format }}</div>
                </div>

                <div>
                    <label :class="label">Težina (g)</label>
                    <input v-model="form.weight_g" type="number" min="1" :class="input" />
                    <div v-if="form.errors.weight_g" :class="error">{{ form.errors.weight_g }}</div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-4">Autori i saradnici</h2>
            <div v-for="(row, index) in form.authors" :key="index" class="mb-3 flex flex-wrap items-start gap-3">
                <div>
                    <select v-model="row.author_id" required :class="input">
                        <option value="">Izaberi autora</option>
                        <option v-for="author in options.authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                    </select>
                    <div v-if="form.errors[`authors.${index}.author_id`]" :class="error">{{ form.errors[`authors.${index}.author_id`] }}</div>
                </div>
                <div>
                    <select v-model="row.role" required :class="input">
                        <option v-for="role in options.roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <div v-if="form.errors[`authors.${index}.role`]" :class="error">{{ form.errors[`authors.${index}.role`] }}</div>
                </div>
                <button type="button" @click="removeAuthor(index)" class="mt-1 px-3 py-2 text-sm text-red-600 hover:text-red-900">Ukloni</button>
            </div>
            <div v-if="form.errors.authors" :class="error">{{ form.errors.authors }}</div>
            <button type="button" @click="addAuthor" class="px-3 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Dodaj autora
            </button>
        </section>

        <div class="flex justify-end gap-4">
            <Link :href="route('admin.books.index')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Otkaži
            </Link>
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
