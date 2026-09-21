<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'category_id' => Category::factory()->active(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->optional()->paragraph(),
            'image' => 'https://picsum.photos/seed/' . Str::slug($name) . '/600/600',
            'price' => $this->faker->randomFloat(2, 5, 100),
            'stock' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * NULL = neograničene zalihe (npr. e-knjiga).
     */
    public function unlimitedStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => null,
        ]);
    }
}
