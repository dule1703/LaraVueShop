<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Author>
 */
class AuthorFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->name();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'bio' => $this->faker->optional()->paragraph(),
            'photo' => null,
        ];
    }
}
