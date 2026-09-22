<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 1: Book / Author / Publisher — factory-ji, relacije i FK ponašanje.
 */
class BookModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_pravi_validnu_knjigu_sa_proizvodom_i_izdavacem(): void
    {
        $book = Book::factory()->create();

        $this->assertInstanceOf(Product::class, $book->product);
        $this->assertInstanceOf(Publisher::class, $book->publisher);
        $this->assertSame(13, strlen($book->isbn13));
        $this->assertContains($book->format, Book::FORMATS);
        $this->assertContains($book->script, Book::SCRIPTS);
        // Faza 4: BookObserver popunjava search_text pri create-u, više nije NULL.
        $this->assertNotEmpty($book->fresh()->search_text);
        $this->assertTrue($book->product->book->is($book));
    }

    public function test_ebook_factory_state_ima_neogranicene_zalihe(): void
    {
        $book = Book::factory()->ebook()->create();

        $this->assertSame('ebook', $book->format);
        $this->assertNull($book->weight_g);
        $this->assertNull($book->product->fresh()->stock);
    }

    public function test_author_i_publisher_factory_prave_validne_zapise(): void
    {
        $author = Author::factory()->create();
        $publisher = Publisher::factory()->create();

        $this->assertDatabaseHas('authors', ['id' => $author->id, 'slug' => $author->slug]);
        $this->assertDatabaseHas('publishers', ['id' => $publisher->id, 'slug' => $publisher->slug]);
    }

    public function test_izdavac_ima_vise_knjiga(): void
    {
        $publisher = Publisher::factory()->create();
        Book::factory()->count(3)->for($publisher)->create();

        $this->assertCount(3, $publisher->books);
    }

    public function test_autori_se_vezuju_sa_ulogom_i_pozicijom_i_vracaju_po_redosledu(): void
    {
        $book = Book::factory()->create();
        [$drugi, $prvi, $prevodilac] = Author::factory()->count(3)->create();

        $book->authors()->attach($drugi, ['role' => 'author', 'position' => 2]);
        $book->authors()->attach($prvi, ['role' => 'author', 'position' => 1]);
        $book->authors()->attach($prevodilac, ['role' => 'translator', 'position' => 3]);

        $authors = $book->authors;

        $this->assertSame([$prvi->id, $drugi->id, $prevodilac->id], $authors->pluck('id')->all());
        $this->assertSame('author', $authors[0]->pivot->role);
        $this->assertSame(1, (int) $authors[0]->pivot->position);
        $this->assertSame('translator', $authors[2]->pivot->role);

        $this->assertSame([$prvi->id, $drugi->id], $book->writers->pluck('id')->all());
        $this->assertTrue($prevodilac->books->first()->is($book));
    }

    public function test_isti_autor_moze_imati_vise_uloga_na_istoj_knjizi(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();

        $book->authors()->attach($author, ['role' => 'author', 'position' => 1]);
        $book->authors()->attach($author, ['role' => 'illustrator', 'position' => 2]);

        $this->assertSame(['author', 'illustrator'], $book->authors->pluck('pivot.role')->all());
    }

    public function test_ista_uloga_istog_autora_se_ne_moze_duplirati(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author, ['role' => 'author', 'position' => 1]);

        $this->expectException(QueryException::class);
        $book->authors()->attach($author, ['role' => 'author', 'position' => 2]);
    }

    public function test_jedan_proizvod_moze_imati_samo_jednu_knjigu(): void
    {
        $book = Book::factory()->create();

        $this->expectException(QueryException::class);
        Book::factory()->create(['product_id' => $book->product_id]);
    }

    public function test_isbn13_je_jedinstven_ali_moze_biti_null_vise_puta(): void
    {
        Book::factory()->count(2)->create(['isbn13' => null]);
        $this->assertSame(2, Book::whereNull('isbn13')->count());

        Book::factory()->create(['isbn13' => '9788652130000']);

        $this->expectException(QueryException::class);
        Book::factory()->create(['isbn13' => '9788652130000']);
    }

    public function test_brisanje_proizvoda_brise_knjigu_i_veze_sa_autorima(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author, ['role' => 'author', 'position' => 1]);

        $book->product->delete();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id]);
        $this->assertDatabaseHas('authors', ['id' => $author->id]);
    }

    public function test_brisanje_izdavaca_ostavlja_knjigu_bez_izdavaca(): void
    {
        $book = Book::factory()->create();

        $book->publisher->delete();

        $this->assertNull($book->fresh()->publisher_id);
    }

    public function test_autor_sa_knjigama_ne_moze_da_se_obrise(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author, ['role' => 'author', 'position' => 1]);

        $this->expectException(QueryException::class);
        $author->delete();
    }

    public function test_search_text_nije_mass_assignable(): void
    {
        $book = Book::factory()->create();

        $book->fill(['search_text' => 'nesto'])->save();

        // fill() tiho ignoriše search_text (nije fillable); BookObserver posle save()-a
        // ionako preračunava kolonu iz naslova/autora/izdavača, pa nikad nije "nesto".
        $fresh = $book->fresh();
        $this->assertNotSame('nesto', $fresh->search_text);
        $this->assertNotEmpty($fresh->search_text);
    }
}
