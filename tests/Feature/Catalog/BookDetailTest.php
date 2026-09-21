<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faza 3.1: stranica jedne knjige na /knjiga/{slug}.
 */
class BookDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_ruta_prikazuje_sve_podatke_o_knjizi(): void
    {
        $publisher = Publisher::factory()->create(['name' => 'Vulkan', 'slug' => 'vulkan']);
        $book = Book::factory()->for($publisher)->for(Product::factory()->state([
            'name' => 'Prokleta avlija', 'slug' => 'prokleta-avlija', 'price' => 12.5, 'stock' => 7,
        ]))->create([
            'isbn13' => '9780306406157', 'subtitle' => 'Roman', 'published_year' => 1954, 'pages' => 120,
            'language' => 'sr', 'script' => 'Cyrl', 'format' => 'paperback',
        ]);
        $andric = Author::factory()->create(['name' => 'Ivo Andrić', 'slug' => 'ivo-andric']);
        $ilustrator = Author::factory()->create(['name' => 'Ilustrator', 'slug' => 'ilustrator']);
        $book->authors()->attach($andric->id, ['role' => 'author', 'position' => 0]);
        $book->authors()->attach($ilustrator->id, ['role' => 'illustrator', 'position' => 1]);

        $this->get(route('book.show', 'prokleta-avlija'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Product')
                ->where('book.product_id', $book->product_id)
                ->where('book.title', 'Prokleta avlija')
                ->where('book.subtitle', 'Roman')
                ->where('book.price', fn ($price) => (float) $price === 12.5)
                ->where('book.stock', 7)
                ->where('book.available', true)
                ->where('book.isbn13', '9780306406157')
                ->where('book.publisher.name', 'Vulkan')
                ->where('book.published_year', 1954)
                ->where('book.pages', 120)
                ->where('book.language', 'sr')
                ->where('book.script', 'Cyrl')
                ->where('book.format', 'paperback')
                ->where('book.authors.0.name', 'Ivo Andrić')
                ->where('book.authors.0.role', 'author')
                ->where('book.authors.1.name', 'Ilustrator')
                ->where('book.authors.1.role', 'illustrator'));
    }

    public function test_slug_ruta_radi_i_za_knjigu_bez_isbn_izdavaca_i_autora(): void
    {
        Book::factory()->withoutPublisher()->for(Product::factory()->state(['name' => 'Rukopis', 'slug' => 'rukopis']))
            ->create(['isbn13' => null, 'isbn10' => null, 'published_year' => null, 'pages' => null, 'script' => null, 'weight_g' => null]);

        $this->get(route('book.show', 'rukopis'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Product')
                ->where('book.title', 'Rukopis')
                ->where('book.isbn13', null)
                ->where('book.publisher', null)
                ->where('book.script', null)
                ->where('book.authors', []));
    }

    public function test_nepostojeci_slug_vraca_404(): void
    {
        $this->get('/knjiga/ne-postoji')->assertNotFound();
    }

    public function test_neaktivna_knjiga_vraca_404(): void
    {
        Book::factory()->for(Product::factory()->inactive()->state(['slug' => 'skrivena']))->create();

        $this->get(route('book.show', 'skrivena'))->assertNotFound();
    }

    public function test_proizvod_koji_nije_knjiga_vraca_404(): void
    {
        Product::factory()->create(['slug' => 'nije-knjiga']);

        $this->get(route('book.show', 'nije-knjiga'))->assertNotFound();
    }

    public function test_slug_nije_id_pa_numericki_pristup_vraca_404(): void
    {
        $book = Book::factory()->create();

        $this->get('/knjiga/'.$book->product_id)->assertNotFound();
    }

    public function test_knjiga_bez_zaliha_nije_dostupna(): void
    {
        Book::factory()->for(Product::factory()->outOfStock()->state(['slug' => 'rasprodata']))->create();

        $this->get(route('book.show', 'rasprodata'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('book.stock', 0)->where('book.available', false));
    }

    public function test_ebook_sa_neogranicenim_zalihama_ima_null_stock_i_dostupan_je(): void
    {
        $book = Book::factory()->ebook()->create();
        $book->product->update(['slug' => 'e-knjiga']);

        $this->get(route('book.show', 'e-knjiga'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('book.format', 'ebook')
                ->where('book.stock', null)
                ->where('book.available', true));
    }

    public function test_stara_id_ruta_vise_ne_postoji(): void
    {
        $book = Book::factory()->create();

        $this->get('/product/'.$book->product_id)->assertNotFound();
    }
}
