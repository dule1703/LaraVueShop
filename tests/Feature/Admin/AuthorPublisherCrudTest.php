<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

class AuthorPublisherCrudTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // --- Autori ---

    public function test_admin_dodaje_autora_a_slug_se_generise_iz_cirilice(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => 'Иво Андрић', 'slug' => '', 'bio' => 'Nobelovac.', 'photo' => 'https://example.com/ivo.jpg'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.authors.index'));

        $this->assertDatabaseHas('authors', ['name' => 'Иво Андрић', 'slug' => 'ivo-andric', 'bio' => 'Nobelovac.']);
    }

    public function test_autor_zahteva_ime_jedinstven_slug_i_ispravan_url_fotografije(): void
    {
        Author::factory()->create(['slug' => 'ivo-andric']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.authors.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.authors.store'), ['name' => 'Ivo Andrić'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('admin.authors.store'), ['name' => 'Neko', 'photo' => 'nije-url'])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('authors', 1);
    }

    public function test_admin_menja_autora_i_zadrzava_svoj_slug(): void
    {
        $author = Author::factory()->create(['slug' => 'stari-slug']);

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), ['name' => 'Novo Ime', 'slug' => 'stari-slug', 'bio' => null, 'photo' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.authors.index'));

        $this->assertDatabaseHas('authors', ['id' => $author->id, 'name' => 'Novo Ime', 'slug' => 'stari-slug']);
    }

    public function test_index_autora_je_paginiran_sa_brojem_knjiga(): void
    {
        $author = Author::factory()->create();
        Book::factory()->count(2)->create()->each(fn (Book $b) => $b->authors()->attach($author->id, ['role' => 'author', 'position' => 0]));
        Author::factory()->count(30)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.authors.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Authors/Index')
                ->has('authors.data', 25)
                ->where('authors.total', 31));
    }

    public function test_autor_bez_knjiga_se_brise(): void
    {
        $author = Author::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.authors.destroy', $author))
            ->assertRedirect(route('admin.authors.index'));

        $this->assertModelMissing($author);
    }

    public function test_autor_sa_knjigama_se_ne_brise_a_ne_puca_sa_500(): void
    {
        $author = Author::factory()->create();
        $book = Book::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->actingAs($this->admin())
            ->delete(route('admin.authors.destroy', $author))
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($author);
        $this->assertDatabaseHas('author_book', ['book_id' => $book->id, 'author_id' => $author->id]);
    }

    // --- Izdavači ---

    public function test_admin_dodaje_izdavaca(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.publishers.store'), ['name' => 'Laguna', 'slug' => '', 'website' => 'https://laguna.rs'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.publishers.index'));

        $this->assertDatabaseHas('publishers', ['name' => 'Laguna', 'slug' => 'laguna', 'website' => 'https://laguna.rs']);
    }

    public function test_izdavac_zahteva_ime_jedinstven_slug_i_ispravan_url(): void
    {
        Publisher::factory()->create(['slug' => 'laguna']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.publishers.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.publishers.store'), ['name' => 'Laguna'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('admin.publishers.store'), ['name' => 'Vulkan', 'website' => 'nije-url'])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('publishers', 1);
    }

    public function test_admin_menja_izdavaca(): void
    {
        $publisher = Publisher::factory()->create(['slug' => 'stari']);

        $this->actingAs($this->admin())
            ->put(route('admin.publishers.update', $publisher), ['name' => 'Novi', 'slug' => 'stari', 'website' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.publishers.index'));

        $this->assertDatabaseHas('publishers', ['id' => $publisher->id, 'name' => 'Novi']);
    }

    public function test_brisanje_izdavaca_ostavlja_knjige_bez_izdavaca(): void
    {
        $publisher = Publisher::factory()->create();
        $book = Book::factory()->for($publisher)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.publishers.destroy', $publisher))
            ->assertRedirect(route('admin.publishers.index'));

        $this->assertModelMissing($publisher);
        $this->assertNull($book->fresh()->publisher_id);
    }
}
