<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Osnovne kategorije – ubaci samo ako ne postoje
        $basicCategories = [
            [
                'name' => 'Elektronika',
                'slug' => 'elektronika',
                'description' => 'Telefoni, računari, televizori i ostala elektronika.',
                'is_active' => true,
            ],
            [
                'name' => 'Knjige',
                'slug' => 'knjige',
                'description' => 'Fikcija, naučna literatura, udžbenici i još mnogo toga.',
                'is_active' => true,
            ],
            [
                'name' => 'Odeća',
                'slug' => 'odeca',
                'description' => 'Muška, ženska i dečija odeća i obuća.',
                'is_active' => true,
            ],
            [
                'name' => 'Kućni aparati',
                'slug' => 'kucni-aparati',
                'description' => 'Frižideri, veš mašine, šporeti i drugi aparati za dom.',
                'is_active' => true,
            ],
        ];

        foreach ($basicCategories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],  // proveri po slug-u
                $cat                       // ako ne postoji, kreiraj sa ovim podacima
            );
        }

        // Random kategorije preko factory-a – samo ako ih nema dovoljno
        if (Category::count() < 20) {
            Category::factory(15)->create();
        }
    }
}