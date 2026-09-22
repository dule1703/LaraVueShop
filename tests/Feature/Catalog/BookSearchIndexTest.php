<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
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
}
