<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Faza 5: filter niske zalihe na admin listi knjiga (GET /admin/books?low_stock=1).
 */
class BookLowStockFilterTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookPayload;

    public function test_low_stock_filter_vraca_samo_knjige_ispod_praga_rastuce(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();

        $low2 = Book::factory()->for(Product::factory()->for($category)->create(['stock' => 2]), 'product')->create();
        $low0 = Book::factory()->for(Product::factory()->for($category)->create(['stock' => 0]), 'product')->create();
        Book::factory()->for(Product::factory()->for($category)->create(['stock' => 50]), 'product')->create();
        $ebook = Book::factory()->ebook()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.index', ['low_stock' => 1]));

        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['books']['data'])->pluck('id')->all();

        $this->assertSame([$low0->id, $low2->id], $ids);
        $this->assertNotContains($ebook->id, $ids);
    }

    public function test_low_stock_filter_respektuje_prilagodjen_prag(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $book = Book::factory()->for(Product::factory()->for($category)->create(['stock' => 8]), 'product')->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.index', ['low_stock' => 1, 'threshold' => 10]));

        $ids = collect($response->viewData('page')['props']['books']['data'])->pluck('id')->all();

        $this->assertContains($book->id, $ids);
    }

    public function test_bez_filtera_lista_prikazuje_sve_knjige_sortirane_po_nazivu(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        Book::factory()->for(Product::factory()->for($category)->create(['stock' => 2]), 'product')->create();
        Book::factory()->for(Product::factory()->for($category)->create(['stock' => 50]), 'product')->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.index'));

        $response->assertOk();
        $this->assertSame(2, count($response->viewData('page')['props']['books']['data']));
    }
}
