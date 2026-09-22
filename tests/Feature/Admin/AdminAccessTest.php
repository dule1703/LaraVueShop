<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pristup admin rutama (Faza 0, korak 1):
 *   gost        -> redirect na login
 *   ne-admin    -> 403, bez ikakve izmene u bazi
 *   admin       -> 200 za GET, uspešan redirect + izmena u bazi za write rute
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Product $product;
    private Order $order;
    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->category = Category::factory()->active()->create();
        $this->product = Product::factory()->for($this->category)->create(['stock' => 3]);
        $this->book = Book::factory()->for($this->product, 'product')->create();
        $this->order = Order::create([
            'first_name' => 'Pera',
            'last_name' => 'Perić',
            'customer_email' => 'pera@example.com',
            'total_price' => 19.99,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
    }

    /**
     * Svaka admin ruta: [HTTP metod, ime rute, model koji ruta prima ili null].
     */
    public static function adminRoutes(): array
    {
        return [
            'categories.index' => ['get', 'admin.categories.index', null],
            'categories.create' => ['get', 'admin.categories.create', null],
            'categories.store' => ['post', 'admin.categories.store', null],
            'categories.show' => ['get', 'admin.categories.show', 'category'],
            'categories.edit' => ['get', 'admin.categories.edit', 'category'],
            'categories.update' => ['put', 'admin.categories.update', 'category'],
            'categories.destroy' => ['delete', 'admin.categories.destroy', 'category'],

            'products.index' => ['get', 'admin.products.index', null],
            'products.create' => ['get', 'admin.products.create', null],
            'products.store' => ['post', 'admin.products.store', null],
            'products.show' => ['get', 'admin.products.show', 'product'],
            'products.edit' => ['get', 'admin.products.edit', 'product'],
            'products.update' => ['put', 'admin.products.update', 'product'],
            'products.destroy' => ['delete', 'admin.products.destroy', 'product'],

            'orders.index' => ['get', 'admin.orders.index', null],
            'orders.show' => ['get', 'admin.orders.show', 'order'],
            'orders.update' => ['put', 'admin.orders.update', 'order'],
            'orders.destroy' => ['delete', 'admin.orders.destroy', 'order'],

            'books.restock' => ['post', 'admin.books.restock', 'book'],
        ];
    }

    public static function adminGetRoutes(): array
    {
        return array_filter(self::adminRoutes(), fn (array $route) => $route[0] === 'get');
    }

    #[DataProvider('adminRoutes')]
    public function test_guest_is_redirected_to_login(string $method, string $routeName, ?string $model): void
    {
        $response = $this->{$method}($this->adminUrl($routeName, $model));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseUnchanged();
    }

    #[DataProvider('adminRoutes')]
    public function test_non_admin_user_gets_403(string $method, string $routeName, ?string $model): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->{$method}($this->adminUrl($routeName, $model));

        $response->assertForbidden();
        $this->assertDatabaseUnchanged();
    }

    #[DataProvider('adminGetRoutes')]
    public function test_admin_can_access_get_route(string $method, string $routeName, ?string $model): void
    {
        $response = $this->actingAs($this->admin())->get($this->adminUrl($routeName, $model));

        $response->assertOk();
    }

    public function test_admin_can_store_category(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Domaći roman',
            'description' => 'Savremena domaća proza.',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Domaći roman', 'slug' => 'domaci-roman']);
    }

    public function test_admin_can_update_category(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.categories.update', $this->category), [
            'name' => 'Poezija',
            'description' => null,
            'is_active' => false,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $this->category->id, 'name' => 'Poezija', 'is_active' => 0]);
    }

    public function test_admin_can_destroy_category(): void
    {
        $response = $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $this->category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertModelMissing($this->category);
    }

    public function test_admin_can_store_product(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Na Drini ćuprija',
            'description' => 'Roman Ive Andrića.',
            'price' => 15.50,
            'stock' => 10,
            'image' => 'https://example.com/drina.jpg',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Na Drini ćuprija', 'stock' => 10]);
    }

    public function test_admin_can_update_product(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), [
            'category_id' => $this->category->id,
            'name' => 'Prokleta avlija',
            'description' => null,
            'price' => 12.00,
            'stock' => 3,
            'image' => 'https://example.com/avlija.jpg',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'name' => 'Prokleta avlija', 'stock' => 3]);
    }

    public function test_admin_can_destroy_product(): void
    {
        $response = $this->actingAs($this->admin())->delete(route('admin.products.destroy', $this->product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertModelMissing($this->product);
    }

    public function test_admin_can_update_order_status(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.orders.show', $this->order))
            ->put(route('admin.orders.update', $this->order), ['status' => 'shipped']);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.orders.show', $this->order));
        $this->assertDatabaseHas('orders', ['id' => $this->order->id, 'status' => 'shipped']);
    }

    public function test_admin_can_destroy_order(): void
    {
        $response = $this->actingAs($this->admin())->delete(route('admin.orders.destroy', $this->order));

        $response->assertRedirect(route('admin.orders.index'));
        $this->assertModelMissing($this->order);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function adminUrl(string $routeName, ?string $model): string
    {
        return route($routeName, $model ? $this->{$model} : []);
    }

    /**
     * Odbijen zahtev ne sme ništa da promeni — ni da kreira, izmeni ni obriše.
     */
    private function assertDatabaseUnchanged(): void
    {
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseHas('categories', ['id' => $this->category->id, 'name' => $this->category->name]);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'name' => $this->product->name, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['id' => $this->order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
