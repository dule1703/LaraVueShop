<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Services\BookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 4: BookObserver/ProductObserver preko BookSearchIndexer-a popunjavaju
 * books.search_text pri create/update (naslov, autori, izdavač).
 */
class BookSearchIndexTest extends TestCase
{
    use RefreshDatabase;

    private function categoryId(): int
    {
        return Category::factory()->active()->create()->id;
    }

    private function makeNamedBook(string $title): Book
    {
        return Book::factory()->for(Product::factory()->state(['name' => $title]))->create();
    }

    public function test_kreiranje_knjige_popunjava_search_text_iz_naslova_autora_i_izdavaca(): void
    {
        $author = Author::factory()->create(['name' => 'Иво Андрић']);
        $publisher = Publisher::factory()->create(['name' => 'Laguna']);

        $book = app(BookService::class)->create(
            ['category_id' => $this->categoryId(), 'name' => 'Na Drini ćuprija', 'slug' => 'na-drini-cuprija', 'price' => 10, 'stock' => 5, 'is_active' => true],
            ['language' => 'sr', 'format' => 'paperback', 'publisher_id' => $publisher->id],
            [['author_id' => $author->id, 'role' => 'author']],
        );

        $searchText = $book->fresh()->search_text;

        $this->assertStringContainsString('na drini cuprija', $searchText);
        $this->assertStringContainsString('ivo andric', $searchText);
        $this->assertStringContainsString('laguna', $searchText);
    }

    public function test_izmena_naslova_azurira_search_text(): void
    {
        $book = Book::factory()->create();
        $book->product->update(['name' => 'Prvobitni naslov']);
        $this->assertStringContainsString('prvobitni naslov', $book->fresh()->search_text);

        app(BookService::class)->update(
            $book->fresh(),
            ['name' => 'Novi naslov', 'price' => $book->product->price, 'category_id' => $book->product->category_id, 'slug' => $book->product->slug, 'stock' => 5, 'is_active' => true],
            ['language' => 'sr', 'format' => 'paperback'],
            [],
        );

        $searchText = $book->fresh()->search_text;
        $this->assertStringContainsString('novi naslov', $searchText);
        $this->assertStringNotContainsString('prvobitni', $searchText);
    }

    public function test_izmena_autora_azurira_search_text(): void
    {
        $book = Book::factory()->create();
        $stari = Author::factory()->create(['name' => 'Stari Pisac']);
        $novi = Author::factory()->create(['name' => 'Novi Pisac']);
        $book->authors()->attach($stari->id, ['role' => 'author', 'position' => 0]);
        $book->touch();
        $this->assertStringContainsString('stari pisac', $book->fresh()->search_text);

        app(BookService::class)->update(
            $book->fresh(),
            ['name' => $book->product->name, 'price' => $book->product->price, 'category_id' => $book->product->category_id, 'slug' => $book->product->slug, 'stock' => 5, 'is_active' => true],
            ['language' => 'sr', 'format' => 'paperback'],
            [['author_id' => $novi->id, 'role' => 'author']],
        );

        $searchText = $book->fresh()->search_text;
        $this->assertStringContainsString('novi pisac', $searchText);
        $this->assertStringNotContainsString('stari pisac', $searchText);
    }

    public function test_izmena_imena_autora_osvezava_search_text_svih_njegovih_knjiga(): void
    {
        $author = Author::factory()->create(['name' => 'Ivo Andric']);
        $prva = $this->makeNamedBook('Prva knjiga');
        $druga = $this->makeNamedBook('Druga knjiga');
        $treca = Book::factory()->create(); // bez ovog autora — ne sme se dirati
        $prva->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);
        $prva->touch();
        $druga->authors()->attach($author->id, ['role' => 'translator', 'position' => 0]);
        $druga->touch();
        $trecaSearchText = $treca->fresh()->search_text;

        $author->update(['name' => 'Ivo Andrić', 'slug' => $author->slug]);

        // Naslov (product_id) mora ostati u search_text-u — regresioni test za bug gde je
        // chunkById(select('books.id')) u observeru brisao product_id/publisher_id pre touch()-a.
        $prvaSearchText = $prva->fresh()->search_text;
        $drugaSearchText = $druga->fresh()->search_text;
        $this->assertStringContainsString('prva knjiga', $prvaSearchText);
        $this->assertStringContainsString('ivo andric', $prvaSearchText);
        $this->assertStringContainsString('druga knjiga', $drugaSearchText);
        $this->assertStringContainsString('ivo andric', $drugaSearchText);
        $this->assertSame($trecaSearchText, $treca->fresh()->search_text);
    }

    public function test_izmena_naziva_izdavaca_osvezava_search_text_njegovih_knjiga(): void
    {
        $publisher = Publisher::factory()->create(['name' => 'Vulkan']);
        $book = Book::factory()->for($publisher)->for(Product::factory()->state(['name' => 'Knjiga kod Vulkana']))->create();
        $drugi = Book::factory()->create(); // drugi izdavač — ne sme se dirati
        $drugiSearchText = $drugi->fresh()->search_text;

        $publisher->update(['name' => 'Vulkan izdavaštvo', 'slug' => $publisher->slug]);

        // Naslov (product_id) mora ostati — isti regresioni razlog kao u testu za autora.
        $searchText = $book->fresh()->search_text;
        $this->assertStringContainsString('knjiga kod vulkana', $searchText);
        $this->assertStringContainsString('vulkan izdavastvo', $searchText);
        $this->assertSame($drugiSearchText, $drugi->fresh()->search_text);
    }

    public function test_izmena_autora_osvezava_search_text_i_preko_granice_chunk_batch_a(): void
    {
        $author = Author::factory()->create(['name' => 'Plodan Pisac']);
        $books = Book::factory()->count(120)->create();
        foreach ($books as $book) {
            $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);
        }
        // Observer nije okinut za attach() iznad (pivot, bez save()-a) — search_text
        // je i dalje bez autora sve dok se ne pokrene izmena ispod.

        $author->update(['name' => 'Veoma Plodan Pisac', 'slug' => $author->slug]);

        $missing = Book::whereIn('id', $books->pluck('id'))
            ->where(function ($q) {
                $q->whereNull('search_text')->orWhere('search_text', 'not like', '%veoma plodan pisac%');
            })
            ->count();

        $this->assertSame(0, $missing);
    }
}
