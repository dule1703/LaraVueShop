<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Product;
use App\Services\BookService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Product + Book + autori su jedna transakcija: ako bilo koji deo upisa pukne,
 * ništa od ostalog ne sme ostati u bazi.
 */
class BookAtomicityTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pukne_upis_knjige_posle_upisa_proizvoda_http(): void
    {
        // Product je već upisan kad Book::creating baci izuzetak.
        Book::creating(fn () => throw new RuntimeException('namerno pokvaren upis knjige'));

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload())
            ->assertStatus(500);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('author_book', 0);
    }

    public function test_pukne_upis_autora_posle_upisa_proizvoda_i_knjige(): void
    {
        // Product i Book su već upisani kad attach() naiđe na nepostojećeg autora (FK greška).
        try {
            app(BookService::class)->create(
                ['category_id' => $this->bookPayload()['category_id'], 'name' => 'X', 'slug' => 'x', 'price' => 1, 'stock' => 1, 'is_active' => true],
                ['language' => 'sr', 'format' => 'paperback'],
                [['author_id' => 999999, 'role' => 'author']],
            );
            $this->fail('Očekivan je izuzetak pri upisu autora.');
        } catch (QueryException) {
            // očekivano
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('author_book', 0);
    }

    public function test_pukne_upis_knjige_zbog_duplog_isbn_na_nivou_baze(): void
    {
        // Zaobilazi validaciju: unique indeks na books.isbn13 puca tek nakon upisa proizvoda.
        Book::factory()->create(['isbn13' => '9780306406157']);

        try {
            app(BookService::class)->create(
                ['category_id' => $this->bookPayload()['category_id'], 'name' => 'Dupli', 'slug' => 'dupli', 'price' => 1, 'stock' => 1, 'is_active' => true],
                ['isbn13' => '9780306406157', 'language' => 'sr', 'format' => 'paperback'],
                [],
            );
            $this->fail('Očekivan je izuzetak zbog duplog ISBN-a.');
        } catch (QueryException) {
            // očekivano
        }

        // Samo proizvod postojeće knjige; novi proizvod ne sme ostati bez knjige.
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseMissing('products', ['slug' => 'dupli']);
        $this->assertDatabaseCount('books', 1);
    }

    public function test_pukne_izmena_autora_pa_se_vraca_i_proizvod_i_knjiga_i_stari_autori(): void
    {
        $original = Author::factory()->create();
        $book = Book::factory()->create(['pages' => 100]);
        $book->authors()->attach($original->id, ['role' => 'author', 'position' => 0]);
        $oldName = $book->product->name;

        try {
            app(BookService::class)->update(
                $book->fresh(),
                ['name' => 'Izmenjeno', 'price' => 99, 'category_id' => $book->product->category_id, 'slug' => $book->product->slug, 'stock' => 5, 'is_active' => true],
                ['pages' => 999, 'language' => 'sr', 'format' => 'paperback'],
                [['author_id' => 999999, 'role' => 'author']],
            );
            $this->fail('Očekivan je izuzetak pri zameni autora.');
        } catch (QueryException) {
            // očekivano
        }

        $this->assertDatabaseHas('products', ['id' => $book->product_id, 'name' => $oldName]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'pages' => 100]);
        $this->assertDatabaseHas('author_book', ['book_id' => $book->id, 'author_id' => $original->id, 'role' => 'author']);
        $this->assertDatabaseCount('author_book', 1);
    }

    public function test_pukne_izmena_knjige_http_pa_proizvod_ostaje_nepromenjen(): void
    {
        $book = Book::factory()->create();
        $oldName = $book->product->name;

        Book::updating(fn () => throw new RuntimeException('namerno pokvarena izmena knjige'));

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->bookPayload(['isbn' => null, 'name' => 'Izmenjeno', 'slug' => 'izmenjeno']))
            ->assertStatus(500);

        $this->assertDatabaseHas('products', ['id' => $book->product_id, 'name' => $oldName]);
        $this->assertDatabaseMissing('products', ['name' => 'Izmenjeno']);
    }

    public function test_uspesan_upis_ostavlja_tacno_po_jedan_red(): void
    {
        $author = Author::factory()->create();

        $book = app(BookService::class)->create(
            ['category_id' => $this->bookPayload()['category_id'], 'name' => 'Ok', 'slug' => 'ok', 'price' => 1, 'stock' => 1, 'is_active' => true],
            ['language' => 'sr', 'format' => 'paperback'],
            [['author_id' => $author->id, 'role' => 'author']],
        );

        $this->assertSame(1, Product::count());
        $this->assertSame(1, Book::count());
        $this->assertSame($book->product_id, Product::first()->id);
        $this->assertDatabaseCount('author_book', 1);
    }
}
