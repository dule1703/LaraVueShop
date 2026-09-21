<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pristup admin rutama za knjige, autore i izdavače (Faza 2), isti obrazac kao AdminAccessTest:
 *   gost -> redirect na login, ne-admin -> 403, admin -> 200 za GET. Odbijen zahtev ne menja bazu.
 */
class AdminCatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;
    private Author $author;
    private Publisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->book = Book::factory()->create();
        $this->author = Author::factory()->create();
        $this->publisher = Publisher::factory()->create();
    }

    /**
     * [HTTP metod, ime rute, model koji ruta prima ili null]
     */
    public static function adminRoutes(): array
    {
        return [
            'books.index' => ['get', 'admin.books.index', null],
            'books.create' => ['get', 'admin.books.create', null],
            'books.store' => ['post', 'admin.books.store', null],
            'books.edit' => ['get', 'admin.books.edit', 'book'],
            'books.update' => ['put', 'admin.books.update', 'book'],
            'books.destroy' => ['delete', 'admin.books.destroy', 'book'],

            'authors.index' => ['get', 'admin.authors.index', null],
            'authors.create' => ['get', 'admin.authors.create', null],
            'authors.store' => ['post', 'admin.authors.store', null],
            'authors.edit' => ['get', 'admin.authors.edit', 'author'],
            'authors.update' => ['put', 'admin.authors.update', 'author'],
            'authors.destroy' => ['delete', 'admin.authors.destroy', 'author'],

            'publishers.index' => ['get', 'admin.publishers.index', null],
            'publishers.create' => ['get', 'admin.publishers.create', null],
            'publishers.store' => ['post', 'admin.publishers.store', null],
            'publishers.edit' => ['get', 'admin.publishers.edit', 'publisher'],
            'publishers.update' => ['put', 'admin.publishers.update', 'publisher'],
            'publishers.destroy' => ['delete', 'admin.publishers.destroy', 'publisher'],
        ];
    }

    public static function adminGetRoutes(): array
    {
        return array_filter(self::adminRoutes(), fn (array $route) => $route[0] === 'get');
    }

    #[DataProvider('adminRoutes')]
    public function test_guest_is_redirected_to_login(string $method, string $routeName, ?string $model): void
    {
        $this->{$method}($this->adminUrl($routeName, $model))->assertRedirect(route('login'));

        $this->assertDatabaseUnchanged();
    }

    #[DataProvider('adminRoutes')]
    public function test_non_admin_user_gets_403(string $method, string $routeName, ?string $model): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->{$method}($this->adminUrl($routeName, $model))->assertForbidden();

        $this->assertDatabaseUnchanged();
    }

    #[DataProvider('adminGetRoutes')]
    public function test_admin_can_access_get_route(string $method, string $routeName, ?string $model): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($this->adminUrl($routeName, $model))
            ->assertOk();
    }

    private function adminUrl(string $routeName, ?string $model): string
    {
        return route($routeName, $model ? $this->{$model} : []);
    }

    private function assertDatabaseUnchanged(): void
    {
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('authors', 1);
        // Book factory pravi svog izdavača, plus jedan izdvojen u setUp-u.
        $this->assertDatabaseCount('publishers', 2);

        $this->assertDatabaseHas('books', ['id' => $this->book->id, 'isbn13' => $this->book->isbn13]);
        $this->assertDatabaseHas('authors', ['id' => $this->author->id, 'name' => $this->author->name]);
        $this->assertDatabaseHas('publishers', ['id' => $this->publisher->id, 'name' => $this->publisher->name]);
    }
}
