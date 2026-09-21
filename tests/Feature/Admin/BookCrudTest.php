<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_index_je_paginiran_i_vraca_knjige_sa_proizvodom_i_autorima(): void
    {
        Book::factory()->count(25)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.books.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Books/Index')
                ->has('books.data', 20)
                ->where('books.total', 25)
                ->has('books.data.0.product.name'));
    }

    public function test_admin_upisuje_product_i_book_zajedno_sa_autorima(): void
    {
        $author = Author::factory()->create();
        $translator = Author::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.books.store'), $this->bookPayload([
            'authors' => [
                ['author_id' => $author->id, 'role' => 'author'],
                ['author_id' => $translator->id, 'role' => 'translator'],
            ],
        ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.books.index'));

        $product = Product::where('slug', 'prokleta-avlija')->firstOrFail();
        $this->assertSame('Prokleta avlija', $product->name);
        $this->assertEquals(12.5, $product->price);
        $this->assertSame(7, $product->stock);
        $this->assertTrue($product->is_active);
        $this->assertSame('https://example.com/avlija.jpg', $product->image);

        $book = $product->book;
        $this->assertNotNull($book);
        $this->assertSame('9780306406157', $book->isbn13);
        $this->assertSame('0306406152', $book->isbn10);
        $this->assertSame(1954, $book->published_year);
        $this->assertSame('sr', $book->language);
        $this->assertSame('Cyrl', $book->script);
        $this->assertSame('paperback', $book->format);

        $this->assertSame(
            [[$author->id, 'author', 0], [$translator->id, 'translator', 1]],
            $book->authors->map(fn (Author $a) => [$a->id, $a->pivot->role, $a->pivot->position])->all(),
        );
    }

    public function test_isbn10_unos_se_cuva_kao_isbn13_i_isbn10(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['isbn' => '0-8044-2957-x']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', ['isbn13' => '9780804429573', 'isbn10' => '080442957X']);
    }

    public function test_isbn_je_opcion_i_slug_se_generise_iz_naslova(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['isbn' => null, 'slug' => '', 'name' => 'Дервиш и смрт']))
            ->assertSessionHasNoErrors();

        $product = Product::where('slug', 'dervis-i-smrt')->firstOrFail();
        $this->assertNull($product->book->isbn13);
        $this->assertNull($product->book->isbn10);
    }

    public function test_nevalidan_isbn_daje_validation_error_i_nista_se_ne_upisuje(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['isbn' => '978-0-306-40615-8']))
            ->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_isbn_sa_slovima_se_ne_odbacuje_tiho(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['isbn' => '978-abc']))
            ->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_duplirani_isbn_je_validation_error_cak_i_kad_je_unet_kao_isbn10(): void
    {
        $existing = Book::factory()->create(['isbn13' => '9780306406157', 'isbn10' => '0306406152']);

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['isbn' => '0-306-40615-2']))
            ->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', ['id' => $existing->id]);
    }

    public function test_stock_je_obavezan_osim_za_ebook(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), $this->bookPayload(['stock' => null]))
            ->assertSessionHasErrors('stock');

        $this->actingAs($admin)
            ->post(route('admin.books.store'), $this->bookPayload(['stock' => null, 'format' => 'ebook', 'isbn' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Product::where('slug', 'prokleta-avlija')->firstOrFail()->stock);
    }

    public function test_slug_mora_biti_jedinstven_i_ispravnog_formata(): void
    {
        Product::factory()->create(['slug' => 'prokleta-avlija']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), $this->bookPayload())
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('admin.books.store'), $this->bookPayload(['slug' => 'Loš Slug!']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_neispravna_polja_knjige_su_odbijena(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'category_id' => 9999,
                'publisher_id' => 9999,
                'format' => 'papirus',
                'script' => 'Greek',
                'language' => 'Serbian',
                'price' => -1,
                'published_year' => 3000,
                'pages' => 0,
            ]))
            ->assertSessionHasErrors(['category_id', 'publisher_id', 'format', 'script', 'language', 'price', 'published_year', 'pages']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_isti_autor_ne_moze_dva_puta_sa_istom_ulogom(): void
    {
        $author = Author::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'authors' => [
                    ['author_id' => $author->id, 'role' => 'author'],
                    ['author_id' => $author->id, 'role' => 'author'],
                ],
            ]))
            ->assertSessionHasErrors('authors');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_nepostojeci_autor_i_nepoznata_uloga_su_odbijeni(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'authors' => [['author_id' => 9999, 'role' => 'proofreader']],
            ]))
            ->assertSessionHasErrors(['authors.0.author_id', 'authors.0.role']);
    }

    public function test_edit_vraca_spljosten_oblik_forme(): void
    {
        $author = Author::factory()->create();
        $book = Book::factory()->create(['isbn13' => '9780306406157']);
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->actingAs($this->admin())
            ->get(route('admin.books.edit', $book))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Books/Edit')
                ->where('book.name', $book->product->name)
                ->where('book.isbn', '9780306406157')
                ->where('book.authors.0.author_id', $author->id)
                ->where('book.authors.0.role', 'author')
                ->has('categories')->has('publishers')->has('authors')
                ->where('formats', Book::FORMATS));
    }

    public function test_admin_menja_product_i_book_zajedno(): void
    {
        $book = Book::factory()->create(['isbn13' => '9780306406157', 'isbn10' => '0306406152']);
        $productId = $book->product_id;

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->bookPayload([
                'name' => 'Nov naslov',
                'slug' => 'nov-naslov',
                'price' => 20,
                'stock' => 3,
                'is_active' => false,
                'isbn' => '978-0-306-40615-7', // isti ISBN na istoj knjizi nije duplikat
                'pages' => 321,
                'format' => 'hardcover',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.books.index'));

        $this->assertDatabaseHas('products', ['id' => $productId, 'name' => 'Nov naslov', 'slug' => 'nov-naslov', 'stock' => 3, 'is_active' => 0]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'product_id' => $productId, 'pages' => 321, 'format' => 'hardcover']);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('books', 1);
    }

    public function test_admin_brise_knjigu_zajedno_sa_proizvodom_i_autorima(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->actingAs($this->admin())
            ->delete(route('admin.books.destroy', $book))
            ->assertRedirect(route('admin.books.index'));

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('author_book', 0);
        $this->assertModelExists($author);
    }

    public function test_knjiga_iz_porudzbine_se_ne_brise(): void
    {
        $book = Book::factory()->create();
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
            'product_id' => $book->product_id,
            'product_name' => $book->product->name,
            'product_price' => 19.99,
            'quantity' => 1,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.books.destroy', $book))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($book);
        $this->assertModelExists($book->product);
    }
}
