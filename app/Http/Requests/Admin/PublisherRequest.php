<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublisherRequest extends FormRequest
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
                Rule::unique('publishers', 'slug')->ignore($this->route('publisher')),
            ],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
