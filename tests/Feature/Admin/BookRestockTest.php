<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Faza 5: ručna dopuna zaliha (admin) — POST /admin/books/{book}/restock.
 */
class BookRestockTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookPayload;

    public function test_admin_moze_da_dopuni_zalihu(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);
        $book = Book::factory()->for($product, 'product')->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('admin.books.restock', $book), [
                'quantity' => 10,
                'note' => 'Nova pošiljka od izdavača',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.books.index'));
        $this->assertEquals(13, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => 10,
            'reason' => 'manual',
            'user_id' => $admin->id,
            'note' => 'Nova pošiljka od izdavača',
            'order_id' => null,
        ]);
    }

    public function test_kolicina_mora_biti_pozitivan_ceo_broj(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create(['stock' => 3]);
        $book = Book::factory()->for($product, 'product')->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.books.restock', $book), ['quantity' => 0]);

        $response->assertSessionHasErrors('quantity');
        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_e_knjiga_sa_neogranicenom_zalihom_ne_moze_da_se_dopuni(): void
    {
        $this->withoutVite();

        $product = Product::factory()->unlimitedStock()->create();
        $book = Book::factory()->for($product, 'product')->create(['format' => 'ebook']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.books.restock', $book), ['quantity' => 5]);

        $response->assertRedirect(route('admin.books.index'));
        $this->assertNull($product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
