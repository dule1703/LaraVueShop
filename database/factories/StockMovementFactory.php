<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'delta' => $this->faker->numberBetween(1, 20),
            'reason' => 'restock',
            'order_id' => null,
            'user_id' => null,
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
