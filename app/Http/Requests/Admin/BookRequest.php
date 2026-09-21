<?php

namespace App\Http\Requests\Admin;

use App\Models\Book;
use App\Rules\ValidIsbn;
use App\Support\Isbn;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // pristup štiti 'admin' middleware na ruti
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'slug' => $slug !== '' ? $slug : Str::slug((string) $this->input('name')),
            'isbn' => is_string($this->input('isbn')) ? Isbn::normalize($this->input('isbn')) : $this->input('isbn'),
        ]);
    }

    public function rules(): array
    {
        $book = $this->route('book');

        return [
            // Product
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($book?->product_id),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // NULL = neograničene zalihe, dozvoljeno samo za e-knjige
            'stock' => [Rule::requiredIf(fn () => $this->input('format') !== 'ebook'), 'nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['required', 'boolean'],

            // Book
            'isbn' => [
                'nullable', new ValidIsbn,
                function (string $attribute, mixed $value, \Closure $fail) use ($book) {
                    $isbn13 = Isbn::toIsbn13((string) $value);
                    if ($isbn13 !== null && Book::where('isbn13', $isbn13)->when($book, fn ($q) => $q->whereKeyNot($book->id))->exists()) {
                        $fail('Knjiga sa ovim ISBN-om već postoji.');
                    }
                },
            ],
            'publisher_id' => ['nullable', 'integer', Rule::exists('publishers', 'id')],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'original_title' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:'.((int) date('Y') + 1)],
            'pages' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}$/'],
            'script' => ['nullable', Rule::in(Book::SCRIPTS)],
            'format' => ['required', Rule::in(Book::FORMATS)],
            'weight_g' => ['nullable', 'integer', 'min:1', 'max:65535'],

            // Autori
            'authors' => ['nullable', 'array', 'max:20'],
            'authors.*.author_id' => ['required', 'integer', Rule::exists('authors', 'id')],
            'authors.*.role' => ['required', Rule::in(Book::AUTHOR_ROLES)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $pairs = collect($this->input('authors', []))
                    ->map(fn ($row) => ($row['author_id'] ?? '').':'.($row['role'] ?? ''));

                if ($pairs->count() !== $pairs->unique()->count()) {
                    $validator->errors()->add('authors', 'Isti autor ne može imati istu ulogu više puta na istoj knjizi.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function productAttributes(): array
    {
        $data = $this->validated();

        return [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock' => $data['stock'] ?? null,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'],
        ];
    }

    /** @return array<string, mixed> */
    public function bookAttributes(): array
    {
        $data = $this->validated();
        $isbn13 = isset($data['isbn']) ? Isbn::toIsbn13($data['isbn']) : null;

        return [
            'isbn13' => $isbn13,
            'isbn10' => $isbn13 !== null ? Isbn::toIsbn10($isbn13) : null,
            'publisher_id' => $data['publisher_id'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'pages' => $data['pages'] ?? null,
            'language' => $data['language'],
            'script' => $data['script'] ?? null,
            'format' => $data['format'],
            'weight_g' => $data['weight_g'] ?? null,
        ];
    }

    /** @return list<array{author_id: int, role: string}> */
    public function authorRows(): array
    {
        return collect($this->validated('authors', []))
            ->map(fn ($row) => ['author_id' => (int) $row['author_id'], 'role' => $row['role']])
            ->values()
            ->all();
    }
}
