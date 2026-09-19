<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'isbn13' => $this->faker->unique()->isbn13(),
            'isbn10' => $this->faker->optional()->isbn10(),
            'publisher_id' => Publisher::factory(),
            'subtitle' => $this->faker->optional()->sentence(4),
            'original_title' => null,
            'published_year' => $this->faker->numberBetween(1950, (int) date('Y')),
            'pages' => $this->faker->numberBetween(64, 900),
            'language' => 'sr',
            'script' => $this->faker->randomElement(['Cyrl', 'Latn']),
            'format' => $this->faker->randomElement(['hardcover', 'paperback']),
            'weight_g' => $this->faker->numberBetween(120, 1500),
        ];
    }

    /**
     * E-knjiga: bez težine, a proizvod ima neograničene zalihe (stock = NULL).
     */
    public function ebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => Product::factory()->unlimitedStock(),
            'format' => 'ebook',
            'weight_g' => null,
        ]);
    }

    public function withoutPublisher(): static
    {
        return $this->state(fn (array $attributes) => [
            'publisher_id' => null,
        ]);
    }

    public function translated(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_title' => $this->faker->sentence(3),
        ]);
    }
}
