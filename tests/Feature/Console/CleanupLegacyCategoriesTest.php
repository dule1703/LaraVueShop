<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupLegacyCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderFor(Product $product): void
    {
        $order = Order::create([
            'total_price' => $product->price,
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'first_name' => 'Pera',
            'last_name' => 'Perić',
            'address' => 'Ulica 1',
            'city' => 'Beograd',
            'postal_code' => '11000',
            'phone' => '0600000000',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
        ]);
    }

    public function test_brise_proizvode_bez_istorije_i_kategoriju(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deaktivira_umesto_brisanja_kad_postoji_order_item(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Clothes and shoes', 'slug' => 'clothes-and-shoes']);
        $product = Product::factory()->for($category)->create();
        $this->makeOrderFor($product);

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_deaktivira_umesto_brisanja_kad_postoji_stock_movement(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Home appliances', 'slug' => 'home-appliances']);
        $product = Product::factory()->for($category)->create();
        StockMovement::factory()->for($product)->create();

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_ne_dira_proizvode_van_ciljanih_kategorija(): void
    {
        $other = Category::factory()->active()->create(['slug' => 'knjige']);
        $product = Product::factory()->for($other)->create();

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $other->id]);
    }

    public function test_dry_run_tacan_broj_i_nista_ne_upisuje(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $toDelete = Product::factory()->for($category)->create();
        $toDeactivate = Product::factory()->for($category)->create();
        $this->makeOrderFor($toDeactivate);

        $this->artisan('catalog:cleanup-legacy-categories', ['--dry-run' => true])
            ->expectsOutputToContain('Obrisalo bi se: 1 proizvod(a), deaktiviralo: 1 proizvod(a)')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $toDelete->id]);
        $this->assertDatabaseHas('products', ['id' => $toDeactivate->id, 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_hard_delete_je_blokiran_kad_ima_order_items_bez_pada_komande(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();
        $this->makeOrderFor($product);

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_je_idempotentna(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Clothes and shoes', 'slug' => 'clothes-and-shoes']);
        $deletable = Product::factory()->for($category)->create();
        $keepable = Product::factory()->for($category)->create();
        $this->makeOrderFor($keepable);

        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();
        $this->assertDatabaseMissing('products', ['id' => $deletable->id]);
        $this->assertDatabaseHas('products', ['id' => $keepable->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        // Drugo pokretanje ne sme da napravi štetu niti da baci grešku.
        $this->artisan('catalog:cleanup-legacy-categories')->assertSuccessful();
        $this->assertDatabaseHas('products', ['id' => $keepable->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_nepostojeca_kategorija_nije_greska(): void
    {
        $this->artisan('catalog:cleanup-legacy-categories', ['--category' => ['nema-je']])->assertSuccessful();
    }

    public function test_moze_da_cilja_prosledjenu_kategoriju_umesto_podrazumevanih(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Sport', 'slug' => 'sport']);
        $product = Product::factory()->for($category)->create();
        $default = Category::factory()->active()->create(['slug' => 'electronics']);
        $keepDefault = Product::factory()->for($default)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--category' => ['sport']])->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('products', ['id' => $keepDefault->id]);
        $this->assertDatabaseHas('categories', ['id' => $default->id]);
    }
}
