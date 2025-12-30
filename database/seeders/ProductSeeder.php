<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Pronađi kategorije po slug-u, ali ako ne postoje – preskoči ili kreiraj fallback
        $elektronika = Category::where('slug', 'elektronika')->first();
        $knjige = Category::where('slug', 'knjige')->first();
        $odeca = Category::where('slug', 'odeca')->first();
        $aparati = Category::where('slug', 'kucni-aparati')->first();

        // Ako neka kategorija ne postoji, preskoči proizvode za nju
        if (!$elektronika && !$knjige && !$odeca && !$aparati) {
            return; // nema kategorija – izađi iz seedera
        }

        $products = [
            // Elektronika
            [
                'category_id' => $elektronika?->id,
                'name' => 'Samsung Galaxy S24 128GB',
                'slug' => 'samsung-galaxy-s24-128gb',
                'description' => 'Najnoviji Samsung flagship telefon sa 8GB RAM-a, 128GB memorije i odličnom kamerom.',
                'price' => 999.99,
                'stock' => 15,
                'is_active' => true,
                'image' => 'https://picsum.photos/600/600?random=1',
            ],
            [
                'category_id' => $elektronika?->id,
                'name' => 'Apple MacBook Air M2 13"',
                'slug' => 'apple-macbook-air-m2-13',
                'description' => 'Lagani laptop sa M2 čipom, 8GB RAM-a i 256GB SSD-om.',
                'price' => 1299.99,
                'stock' => 8,
                'is_active' => true,
                'image' => 'https://picsum.photos/600/600?random=2',
            ],
            [
                'category_id' => $elektronika?->id,
                'name' => 'Sony WH-1000XM5 slušalice',
                'slug' => 'sony-wh-1000xm5-slusalice',
                'description' => 'Najbolje noise-cancelling slušalice na tržištu.',
                'price' => 399.99,
                'stock' => 20,
                'is_active' => true,
                'image' => 'https://picsum.photos/600/600?random=3',
            ],
            [
                'category_id' => $elektronika?->id,
                'name' => 'LG OLED 55" 4K TV',
                'slug' => 'lg-oled-55-4k-tv',
                'description' => 'OLED televizor sa savršenim crnim bojama.',
                'price' => 1499.99,
                'stock' => 5,
                'is_active' => true,
                'image' => 'https://picsum.photos/600/600?random=4',
            ],

            // Knjige
            [
                'category_id' => $knjige?->id,
                'name' => 'Dina - Frenk Herbert',
                'slug' => 'dina-frenk-herbert',
                'description' => 'Kultni SF roman, remek-delo žanra.',
                'price' => 19.99,
                'stock' => 30,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/book1/600/600',
            ],
            [
                'category_id' => $knjige?->id,
                'name' => '1984 - Džordž Orvel',
                'slug' => '1984-djordz-orvel',
                'description' => 'Distopijski klasik o totalitarizmu.',
                'price' => 14.99,
                'stock' => 25,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/book2/600/600',
            ],
            [
                'category_id' => $knjige?->id,
                'name' => 'Atomic Habits - Džejms Klir',
                'slug' => 'atomic-habits-dzejms-klir',
                'description' => 'Praktičan vodič za stvaranje dobrih navika.',
                'price' => 22.99,
                'stock' => 40,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/book3/600/600',
            ],

            // Odeća
            [
                'category_id' => $odeca?->id,
                'name' => 'Nike Air Force 1 patike',
                'slug' => 'nike-air-force-1-patike',
                'description' => 'Klasične bele patike, udobne i stilizovane.',
                'price' => 119.99,
                'stock' => 50,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/shoes1/600/600',
            ],
            [
                'category_id' => $odeca?->id,
                'name' => 'Zara zimski kaput',
                'slug' => 'zara-zimski-kaput',
                'description' => 'Elegantan vuneni kaput za hladne dane.',
                'price' => 149.99,
                'stock' => 12,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/coat1/600/600',
            ],

            // Kućni aparati
            [
                'category_id' => $aparati?->id,
                'name' => 'Bosch veš mašina 9kg',
                'slug' => 'bosch-ves-masina-9kg',
                'description' => 'Energetski efikasna veš mašina sa inverter motorom.',
                'price' => 599.99,
                'stock' => 7,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/appliance1/600/600',
            ],
            [
                'category_id' => $aparati?->id,
                'name' => 'Philips espresso aparat',
                'slug' => 'philips-espresso-aparat',
                'description' => 'Automatski aparat za kafu sa mlekom.',
                'price' => 449.99,
                'stock' => 10,
                'is_active' => true,
                'image' => 'https://picsum.photos/seed/coffee1/600/600',
            ],
        ];

        foreach ($products as $product) {
            if (!$product['category_id']) {
                continue;
            }

            $existing = Product::where('slug', $product['slug'])->first();

            if ($existing) {
                // Ako postoji – ažuriraj samo image (i eventualno druga polja ako želiš)
                $existing->update([
                    'image' => $product['image'],
                ]);
            } else {
                // Ako ne postoji – kreiraj novi
                Product::create($product);
            }
        }
    }
}