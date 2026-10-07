<?php

namespace Tests\Feature\Console;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\BookCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class CleanupLegacyCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRM = 'Nastaviti sa soft brisanjem iz plana?';

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

    public function test_soft_brise_proizvode_bez_istorije_i_kategoriju(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($category);
    }

    public function test_soft_brise_i_proizvode_sa_order_item_i_cuva_istoriju(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Clothes and shoes', 'slug' => 'clothes-and-shoes']);
        $product = Product::factory()->for($category)->create();
        $this->makeOrderFor($product);

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($category);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id]);
        $this->assertSame($product->id, OrderItem::where('product_id', $product->id)->first()->product->id);
    }

    public function test_soft_brise_i_proizvode_sa_stock_movement(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Home appliances', 'slug' => 'home-appliances']);
        $product = Product::factory()->for($category)->create();
        StockMovement::factory()->for($product)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($category);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id]);
    }

    public function test_obrisana_kategorija_se_ne_prikazuje_u_katalog_filterima(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();
        $this->makeOrderFor($product);

        $this->assertContains($category->slug, collect((new BookCatalog)->options()['categories'])->pluck('slug'));

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertNotContains($category->slug, collect((new BookCatalog)->options()['categories'])->pluck('slug'));
    }

    public function test_ne_dira_proizvode_van_ciljanih_kategorija(): void
    {
        $other = Category::factory()->active()->create(['slug' => 'knjige']);
        $product = Product::factory()->for($other)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($other);
    }

    public function test_potkategorije_se_brisu_pre_roditelja(): void
    {
        $parent = Category::factory()->active()->create(['slug' => 'electronics']);
        $child = Category::factory()->childOf($parent)->create();
        $product = Product::factory()->for($child)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($child);
        $this->assertSoftDeleted($parent);
    }

    public function test_dry_run_ispisuje_plan_ne_pita_i_nista_ne_upisuje(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $plain = Product::factory()->for($category)->create();
        $withOrder = Product::factory()->for($category)->create();
        $this->makeOrderFor($withOrder);

        $this->artisan('catalog:cleanup-legacy-categories', ['--dry-run' => true])
            ->expectsOutputToContain('Proizvoda za soft delete: 2')
            ->expectsOutputToContain('Pogođenih order_items: 1 (porudžbina: 1)')
            ->expectsOutputToContain('DRY RUN')
            ->doesntExpectOutputToContain('Soft-obrisano:')
            ->assertSuccessful();

        $this->assertNotSoftDeleted($plain);
        $this->assertNotSoftDeleted($withOrder);
        $this->assertNotSoftDeleted($category);
    }

    public function test_odbijena_potvrda_ne_menja_nista(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();

        $this->artisan('catalog:cleanup-legacy-categories')
            ->expectsConfirmation(self::CONFIRM, 'no')
            ->expectsOutputToContain('Prekinuto')
            ->assertSuccessful();

        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($category);
    }

    public function test_prihvacena_potvrda_izvrsava_brisanje(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();

        $this->artisan('catalog:cleanup-legacy-categories')
            ->expectsConfirmation(self::CONFIRM, 'yes')
            ->expectsOutputToContain('Soft-obrisano: 1 proizvod(a), 1 kategorija.')
            ->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($category);
    }

    public function test_bez_force_u_neinteraktivnom_rezimu_ne_brise(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $product = Product::factory()->for($category)->create();

        // Bez mock-a OutputStyle-a (artisan() ga zamenjuje) — pravi neinteraktivni poziv.
        $exit = Artisan::call('catalog:cleanup-legacy-categories', ['--no-interaction' => true]);

        $this->assertSame(0, $exit);

        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($category);
    }

    public function test_books_nije_u_podrazumevanoj_listi(): void
    {
        $books = Category::factory()->active()->create(['name' => 'Books', 'slug' => 'books']);
        $product = Product::factory()->for($books)->create();
        $this->makeOrderFor($product);

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();

        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($books);
    }

    public function test_category_books_soft_brise_proizvode_pa_kategoriju_i_ispisuje_brojeve(): void
    {
        $books = Category::factory()->active()->create(['name' => 'Books', 'slug' => 'books']);
        $withOrder = Product::factory()->for($books)->create();
        $this->makeOrderFor($withOrder);
        $plain = Product::factory()->for($books)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--category' => ['books']])
            ->expectsOutputToContain('Proizvoda za soft delete: 2')
            ->expectsOutputToContain('Pogođenih order_items: 1 (porudžbina: 1)')
            ->expectsOutputToContain('UPOZORENJE: meta uključuje kategoriju "books"')
            ->expectsConfirmation(self::CONFIRM, 'yes')
            ->assertSuccessful();

        $this->assertSoftDeleted($withOrder);
        $this->assertSoftDeleted($plain);
        $this->assertSoftDeleted($books);
        $this->assertDatabaseHas('order_items', ['product_id' => $withOrder->id]);
    }

    public function test_force_bez_allow_book_delete_staje_kad_bi_book_redovi_bili_obrisani(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $book = Book::factory()->for(Product::factory()->for($category)->create(), 'product')->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])
            ->expectsOutputToContain('Book redova za TVRDO brisanje: 1')
            ->expectsOutputToContain('--allow-book-delete')
            ->assertFailed();

        $this->assertNotSoftDeleted($book->product);
        $this->assertNotSoftDeleted($category);
        $this->assertModelExists($book);
    }

    public function test_sa_allow_book_delete_prolazi_i_tvrdo_brise_book(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $book = Book::factory()->for(Product::factory()->for($category)->create(), 'product')->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true, '--allow-book-delete' => true])
            ->assertSuccessful();

        $this->assertSoftDeleted('products', ['id' => $book->product_id]);
        $this->assertSoftDeleted($category);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_allow_book_delete_ne_zamenjuje_potvrdu(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $book = Book::factory()->for(Product::factory()->for($category)->create(), 'product')->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--allow-book-delete' => true])
            ->expectsConfirmation(self::CONFIRM, 'no')
            ->assertSuccessful();

        $this->assertModelExists($book);
        $this->assertNotSoftDeleted($category);
    }

    public function test_dry_run_sa_book_redovima_ne_staje_ali_upozorava(): void
    {
        $category = Category::factory()->active()->create(['slug' => 'electronics']);
        $book = Book::factory()->for(Product::factory()->for($category)->create(), 'product')->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--dry-run' => true])
            ->expectsOutputToContain('Pravo pokretanje bi tražilo --allow-book-delete')
            ->assertSuccessful();

        $this->assertModelExists($book);
    }

    public function test_transakcija_po_korenskoj_kategoriji_vraca_sve_kad_brisanje_padne(): void
    {
        $electronics = Category::factory()->active()->create(['slug' => 'electronics']);
        $product = Product::factory()->for($electronics)->create();

        Category::deleting(function () {
            throw new RuntimeException('simulirani pad');
        });

        try {
            $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->run();
            $this->fail('Očekivan izuzetak');
        } catch (RuntimeException $e) {
            $this->assertSame('simulirani pad', $e->getMessage());
        }

        // Proizvod se vraća zajedno sa kategorijom (jedna transakcija po korenu).
        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($electronics);
    }

    public function test_je_idempotentna(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Clothes and shoes', 'slug' => 'clothes-and-shoes']);
        $plain = Product::factory()->for($category)->create();
        $withOrder = Product::factory()->for($category)->create();
        $this->makeOrderFor($withOrder);

        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])->assertSuccessful();
        $this->assertSoftDeleted($plain);
        $this->assertSoftDeleted($withOrder);
        $this->assertSoftDeleted($category);

        // Drugo pokretanje ne sme da napravi štetu niti da baci grešku.
        $this->artisan('catalog:cleanup-legacy-categories', ['--force' => true])
            ->expectsOutputToContain('Nema šta da se očisti.')
            ->assertSuccessful();
        $this->assertSame(2, Product::onlyTrashed()->count());
        $this->assertSame(1, Category::onlyTrashed()->count());
    }

    public function test_nepostojeca_kategorija_nije_greska(): void
    {
        $this->artisan('catalog:cleanup-legacy-categories', ['--category' => ['nema-je'], '--force' => true])->assertSuccessful();
    }

    public function test_moze_da_cilja_prosledjenu_kategoriju_umesto_podrazumevanih(): void
    {
        $category = Category::factory()->active()->create(['name' => 'Sport', 'slug' => 'sport']);
        $product = Product::factory()->for($category)->create();
        $default = Category::factory()->active()->create(['slug' => 'electronics']);
        $keepDefault = Product::factory()->for($default)->create();

        $this->artisan('catalog:cleanup-legacy-categories', ['--category' => ['sport'], '--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($product);
        $this->assertNotSoftDeleted($keepDefault);
        $this->assertNotSoftDeleted($default);
    }
}
