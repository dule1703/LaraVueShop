<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Publisher;
use App\Models\User;

trait BuildsBookPayload
{
    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Ispravan zahtev za admin formu knjige; prepiši samo ono što test menja.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function bookPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => (Category::query()->first() ?? Category::factory()->active()->create())->id,
            'name' => 'Prokleta avlija',
            'slug' => 'prokleta-avlija',
            'description' => 'Roman Ive Andrića.',
            'price' => 12.5,
            'stock' => 7,
            'image' => 'https://example.com/avlija.jpg',
            'is_active' => true,
            'isbn' => '978-0-306-40615-7',
            'publisher_id' => (Publisher::query()->first() ?? Publisher::factory()->create())->id,
            'subtitle' => null,
            'original_title' => null,
            'published_year' => 1954,
            'pages' => 120,
            'language' => 'sr',
            'script' => 'Cyrl',
            'format' => 'paperback',
            'weight_g' => 300,
            'authors' => [],
        ], $overrides);
    }
}
