<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Elektronika
        $elektronika = Category::where('slug', 'elektronika')->first();

        Product::create([
            'category_id' => $elektronika->id,
            'name' => 'Samsung Galaxy S24 128GB',
            'slug' => 'samsung-galaxy-s24-128gb',
            'description' => 'Najnoviji Samsung flagship telefon sa 8GB RAM-a, 128GB memorije i odličnom kamerom.',
            'price' => 999.99,
            'stock' => 15,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $elektronika->id,
            'name' => 'Apple MacBook Air M2 13"',
            'slug' => 'apple-macbook-air-m2-13',
            'description' => 'Lagani laptop sa M2 čipom, 8GB RAM-a i 256GB SSD-om. Idealno za svakodnevni rad.',
            'price' => 1299.99,
            'stock' => 8,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $elektronika->id,
            'name' => 'Sony WH-1000XM5 slušalice',
            'slug' => 'sony-wh-1000xm5-slusalice',
            'description' => 'Najbolje noise-cancelling slušalice na tržištu sa 30 sati baterije.',
            'price' => 399.99,
            'stock' => 20,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $elektronika->id,
            'name' => 'LG OLED 55" 4K TV',
            'slug' => 'lg-oled-55-4k-tv',
            'description' => 'OLED televizor sa savršenim crnim bojama i Dolby Vision podrškom.',
            'price' => 1499.99,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Knjige
        $knjige = Category::where('slug', 'knjige')->first();

        Product::create([
            'category_id' => $knjige->id,
            'name' => 'Dina - Frenk Herbert',
            'slug' => 'dina-frenk-herbert',
            'description' => 'Kultni SF roman, remek-delo žanra.',
            'price' => 19.99,
            'stock' => 30,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $knjige->id,
            'name' => '1984 - Džordž Orvel',
            'slug' => '1984-djordz-orvel',
            'description' => 'Distopijski klasik o totalitarizmu i nadzoru.',
            'price' => 14.99,
            'stock' => 25,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $knjige->id,
            'name' => 'Atomic Habits - Džejms Klir',
            'slug' => 'atomic-habits-dzejms-klir',
            'description' => 'Praktičan vodič za stvaranje dobrih navika.',
            'price' => 22.99,
            'stock' => 40,
            'is_active' => true,
        ]);

        // Odeća
        $odeca = Category::where('slug', 'odeca')->first();

        Product::create([
            'category_id' => $odeca->id,
            'name' => 'Nike Air Force 1 patike',
            'slug' => 'nike-air-force-1-patike',
            'description' => 'Klasične bele patike, udobne i stilizovane.',
            'price' => 119.99,
            'stock' => 50,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $odeca->id,
            'name' => 'Zara zimski kaput',
            'slug' => 'zara-zimski-kaput',
            'description' => 'Elegantan vuneni kaput za hladne dane.',
            'price' => 149.99,
            'stock' => 12,
            'is_active' => true,
        ]);

        // Kućni aparati
        $aparati = Category::where('slug', 'kucni-aparati')->first();

        Product::create([
            'category_id' => $aparati->id,
            'name' => 'Bosch veš mašina 9kg',
            'slug' => 'bosch-ves-masina-9kg',
            'description' => 'Energetski efikasna veš mašina sa inverter motorom.',
            'price' => 599.99,
            'stock' => 7,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $aparati->id,
            'name' => 'Philips espresso aparat',
            'slug' => 'philips-espresso-aparat',
            'description' => 'Automatski aparat za kafu sa mlekom.',
            'price' => 449.99,
            'stock' => 10,
            'is_active' => true,
        ]);
    }
}