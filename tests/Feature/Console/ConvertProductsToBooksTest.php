<?php

namespace Tests\Feature\Console;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertProductsToBooksTest extends TestCase
{
    use RefreshDatabase;

    private Category $books;

    protected function setUp(): void
    {
        parent::setUp();

        $this->books = Category::factory()->active()->create(['name' => 'Knjige', 'slug' => 'knjige']);
    }

    public function test_prebacuje_proizvode_iz_kategorije_knjige_i_njenih_potkategorija(): void
    {
        $child = Category::factory()->active()->create(['parent_id' => $this->books->id]);
        $grandchild = Category::factory()->active()->create(['parent_id' => $child->id]);
        $top = Product::factory()->for($this->books)->create(['price' => 19.99, 'stock' => 30]);
        $nested = Product::factory()->for($grandchild)->create();

        $this->artisan('catalog:convert-products-to-books')->assertSuccessful();

        $this->assertDatabaseHas('books', ['product_id' => $top->id, 'language' => 'sr', 'format' => 'paperback']);
        $this->assertDatabaseHas('books', ['product_id' => $nested->id]);
        $this->assertDatabaseCount('books', 2);
    }

    public function test_ne_dira_proizvode_van_kategorije_knjiga(): void
    {
        $other = Product::factory()->for(Category::factory()->active()->create(['slug' => 'elektronika']))->create();

        $this->artisan('catalog:convert-products-to-books')->assertSuccessful();

        $this->assertDatabaseMissing('books', ['product_id' => $other->id]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_ne_menja_cenu_zalihe_ni_ostala_polja_proizvoda(): void
    {
        $product = Product::factory()->for($this->books)->create(['price' => 19.99, 'stock' => 30, 'is_active' => false]);
        $before = $product->fresh()->getAttributes();

        $this->artisan('catalog:convert-products-to-books')->assertSuccessful();

        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_je_idempotentna_i_ne_prepisuje_postojece_knjige(): void
    {
        $plain = Product::factory()->for($this->books)->create();
        $existing = Book::factory()->create(['pages' => 250, 'language' => 'en']);
        $existing->product->update(['category_id' => $this->books->id]);

        $this->artisan('catalog:convert-products-to-books')->assertSuccessful();
        $this->assertDatabaseCount('books', 2);
        $snapshot = Book::orderBy('id')->get()->map->getAttributes()->all();

        $this->artisan('catalog:convert-products-to-books')->assertSuccessful();

        $this->assertSame($snapshot, Book::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseHas('books', ['id' => $existing->id, 'pages' => 250, 'language' => 'en']);
        $this->assertDatabaseHas('books', ['product_id' => $plain->id]);
    }

    public function test_dry_run_nista_ne_upisuje(): void
    {
        Product::factory()->for($this->books)->count(2)->create();

        $this->artisan('catalog:convert-products-to-books', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_opcije_jezik_i_format_se_upisuju(): void
    {
        $product = Product::factory()->for($this->books)->create();

        $this->artisan('catalog:convert-products-to-books', ['--language' => 'en', '--format' => 'ebook'])->assertSuccessful();

        $this->assertDatabaseHas('books', ['product_id' => $product->id, 'language' => 'en', 'format' => 'ebook']);
    }

    public function test_nevalidne_opcije_prekidaju_komandu_bez_upisa(): void
    {
        Product::factory()->for($this->books)->create();

        $this->artisan('catalog:convert-products-to-books', ['--format' => 'papirus'])->assertFailed();
        $this->artisan('catalog:convert-products-to-books', ['--language' => 'Serbian'])->assertFailed();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_nepostojeca_kategorija_nije_greska(): void
    {
        $this->artisan('catalog:convert-products-to-books', ['--category' => 'nema-je'])->assertSuccessful();

        $this->assertDatabaseCount('books', 0);
    }
}
