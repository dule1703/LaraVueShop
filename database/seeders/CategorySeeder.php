<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ručno dodaj nekoliko osnovnih kategorija (da budu sigurno tu)
        Category::create([
            'name' => 'Elektronika',
            'slug' => 'elektronika',
            'description' => 'Telefoni, računari, televizori i ostala elektronika.',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Knjige',
            'slug' => 'knjige',
            'description' => 'Fikcija, naučna literatura, udžbenici i još mnogo toga.',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Odeća',
            'slug' => 'odeca',
            'description' => 'Muška, ženska i dečija odeća i obuća.',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Kućni aparati',
            'slug' => 'kucni-aparati',
            'description' => 'Frižideri, veš mašine, šporeti i drugi aparati za dom.',
            'is_active' => true,
        ]);

        // Dodaj još 10-15 random kategorija preko factory-a
        Category::factory(15)->create();
    }
}