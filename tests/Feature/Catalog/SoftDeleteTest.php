<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Soft delete proizvoda/kategorija (umesto FK 1451 na order_items) i
 * brisanje povezane knjige (nema siročadi Book redova).
 */
class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookPayload;

    private function orderFor(Product $product, int $quantity = 1): Order
    {
        $order = Order::create([
            'first_name' => 'Pera',
            'last_name' => 'Perić',
            'customer_email' => 'pera@example.com',
            'total_price' => 19.99,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 19.99,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    public function test_soft_delete_proizvoda_brise_knjigu_i_oslobadja_isbn(): void
    {
        $book = Book::factory()->create(['isbn13' => '9780306406157']);
        $author = Author::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $book->product->delete();

        $this->assertSoftDeleted('products', ['id' => $book->product_id]);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id]);
        $this->assertModelExists($author);

        // Isti ISBN može ponovo da se unese (unique indeks je slobodan).
        $again = Book::factory()->create(['isbn13' => '9780306406157']);
        $this->assertSame('9780306406157', $again->isbn13);
    }

    public function test_soft_delete_preimenuje_slug_pa_se_isti_slug_moze_ponovo_koristiti(): void
    {
        $product = Product::factory()->create(['slug' => 'prokleta-avlija']);

        $product->delete();

        $this->assertSame('slug-deleted-'.$product->id, Product::withTrashed()->find($product->id)->slug);
        $this->assertSame('slug-deleted-'.$product->id, $product->slug);
        Product::factory()->create(['slug' => 'prokleta-avlija']);

        $category = Category::factory()->create(['slug' => 'poezija']);
        $category->delete();
        $this->assertSame('slug-deleted-'.$category->id, Category::withTrashed()->find($category->id)->slug);
        Category::factory()->create(['slug' => 'poezija']);
    }

    public function test_force_delete_ne_puca_i_knjiga_nestaje_kaskadno(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $book->product->forceDelete();

        $this->assertDatabaseMissing('products', ['id' => $book->product_id]);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id]);
    }

    public function test_pad_brisanja_knjige_ponistava_soft_delete_proizvoda(): void
    {
        $book = Book::factory()->create();
        $product = $book->product;
        $slug = $product->slug;

        Book::deleting(function () {
            throw new RuntimeException('simulirani pad');
        });

        try {
            $product->delete();
            $this->fail('Očekivan izuzetak');
        } catch (RuntimeException $e) {
            $this->assertSame('simulirani pad', $e->getMessage());
        }

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => $slug]);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_admin_brise_proizvod_iz_porudzbine_a_stara_porudzbina_ostaje_citljiva(): void
    {
        $this->withoutVite();

        $product = Product::factory()->create();
        $order = $this->orderFor($product);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($product);
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertSame($product->id, $item->product->id); // withTrashed
        $this->assertNotNull($item->product->deleted_at);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_kategorija_sa_proizvodima_ili_potkategorijama_se_ne_brise(): void
    {
        $admin = $this->admin();
        $withProduct = Category::factory()->create();
        Product::factory()->for($withProduct)->create();
        $withChild = Category::factory()->create();
        Category::factory()->childOf($withChild)->create();

        foreach ([$withProduct, $withChild] as $category) {
            $this->actingAs($admin)
                ->delete(route('admin.categories.destroy', $category))
                ->assertRedirect(route('admin.categories.index'))
                ->assertSessionHas('error');

            $this->assertNotSoftDeleted($category);
        }
    }

    public function test_prazna_kategorija_se_soft_brise(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($category);
    }

    public function test_kategorija_ciji_je_proizvod_obrisan_moze_da_se_obrise(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $this->orderFor($product);
        $product->delete();

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($category);
        // Obrisan proizvod i dalje vidi svoju (obrisanu) kategoriju.
        $this->assertSame($category->id, Product::withTrashed()->find($product->id)->category->id);
    }

    public function test_obrisan_proizvod_nije_u_katalogu_korpi_ni_admin_listama(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $live = Book::factory()->for(Product::factory()->for($category)->create(['name' => 'Živa knjiga']), 'product')->create();
        $dead = Book::factory()->for(Product::factory()->for($category)->create(['name' => 'Obrisana knjiga']), 'product')->create();
        $deadProduct = $dead->product;
        $deadSlug = $deadProduct->slug;

        $deadProduct->delete();

        $shop = $this->get(route('shop'))->assertOk();
        $this->assertStringNotContainsString('Obrisana knjiga', json_encode($shop->viewData('page')['props'], JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('Živa knjiga', json_encode($shop->viewData('page')['props'], JSON_UNESCAPED_UNICODE));

        $this->get(route('book.show', $deadSlug))->assertNotFound();
        $this->get(route('book.show', 'slug-deleted-'.$deadProduct->id))->assertNotFound();

        $admin = $this->actingAs($this->admin())->get(route('admin.books.index'))->assertOk();
        $this->assertStringNotContainsString('Obrisana knjiga', json_encode($admin->viewData('page')['props'], JSON_UNESCAPED_UNICODE));

        $cart = $this->getJson('/api/cart/products?'.http_build_query(['ids' => [$live->product_id, $deadProduct->id]]));
        $this->assertSame([$live->product_id], collect($cart->json('products'))->pluck('id')->all());
    }

    public function test_join_guard_sakriva_knjigu_ciji_je_proizvod_soft_obrisan_mimo_observera(): void
    {
        $this->withoutVite();

        $book = Book::factory()->for(Product::factory()->create(['name' => 'Siroče']), 'product')->create();
        // Direktan upit zaobilazi model evente — simulira siroče Book red.
        DB::table('products')->where('id', $book->product_id)->update(['deleted_at' => now()]);

        $shop = $this->get(route('shop'))->assertOk();
        $this->assertStringNotContainsString('Siroče', json_encode($shop->viewData('page')['props'], JSON_UNESCAPED_UNICODE));

        $admin = $this->actingAs($this->admin())->get(route('admin.books.index'))->assertOk();
        $this->assertSame([], $admin->viewData('page')['props']['books']['data']);
    }

    public function test_povrat_zaliha_radi_i_za_soft_obrisan_proizvod(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->orderFor($product, 2);
        $product->delete();

        app(InventoryService::class)->restoreStock($order->load('items'), 'cancel', 'cancelled');

        $this->assertSame(5, (int) Product::withTrashed()->find($product->id)->stock);
    }

    public function test_porudzbina_ne_moze_da_kupi_obrisan_proizvod(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $product->delete();

        $this->postJson(route('orders.store'), [
            'items' => [['id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cod',
            'customer_email' => 'pera@example.com',
            'shipping' => [
                'recipient_name' => 'Pera Perić', 'phone' => '0601234567', 'line1' => 'Ulica 1',
                'city' => 'Beograd', 'postal_code' => '11000',
            ],
        ])->assertStatus(422);

        $this->assertSame(5, (int) Product::withTrashed()->find($product->id)->stock);
    }
}
