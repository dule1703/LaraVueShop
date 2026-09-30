<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // pristup štiti 'admin' middleware na ruti
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge(['slug' => $slug !== '' ? $slug : Str::slug((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('authors', 'slug')->ignore($this->route('author')),
            ],
            'bio' => ['nullable', 'string'],
            // Rešavanje stvarne authors.photo vrednosti (upload/remove_photo/
            // nedirnuto) ide preko ProductImageUploader-a u AuthorController-u,
            // ne ovde — isti razlog kao 'image' u BookRequest-u (FormRequest
            // nema pristup postojećoj vrednosti bez route-bound $author).
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
