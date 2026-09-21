<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * author_book ima PK (book_id, author_id, role): veza se menja isključivo sa detach() + attach().
 */
class BookAuthorPivotTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function updateAuthors(Book $book, array $authors): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->bookPayload([
                'isbn' => null,
                'name' => $book->product->name,
                'slug' => $book->product->slug,
                'authors' => $authors,
            ]))
            ->assertSessionHasNoErrors();
    }

    private function pivotRows(Book $book): array
    {
        return $book->fresh()->authors->map(fn (Author $a) => [$a->id, $a->pivot->role, $a->pivot->position])->all();
    }

    public function test_isti_autor_moze_imati_dve_uloge_na_istoj_knjizi(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();

        $this->updateAuthors($book, [
            ['author_id' => $author->id, 'role' => 'author'],
            ['author_id' => $author->id, 'role' => 'illustrator'],
        ]);

        $this->assertSame([[$author->id, 'author', 0], [$author->id, 'illustrator', 1]], $this->pivotRows($book));
    }

    public function test_izmena_uklanja_stare_i_dodaje_nove_autore(): void
    {
        $book = Book::factory()->create();
        [$a, $b, $c] = Author::factory()->count(3)->create();
        $book->authors()->attach($a->id, ['role' => 'author', 'position' => 0]);
        $book->authors()->attach($b->id, ['role' => 'author', 'position' => 1]);

        $this->updateAuthors($book, [['author_id' => $c->id, 'role' => 'author']]);

        $this->assertSame([[$c->id, 'author', 0]], $this->pivotRows($book));
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id, 'author_id' => $a->id]);
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id, 'author_id' => $b->id]);
    }

    public function test_promena_uloge_istog_autora_zamenjuje_red_a_ne_dodaje_novi(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->updateAuthors($book, [['author_id' => $author->id, 'role' => 'translator']]);

        $this->assertSame([[$author->id, 'translator', 0]], $this->pivotRows($book));
        $this->assertDatabaseCount('author_book', 1);
    }

    public function test_redosled_iz_forme_postaje_position(): void
    {
        $book = Book::factory()->create();
        [$a, $b] = Author::factory()->count(2)->create();

        $this->updateAuthors($book, [
            ['author_id' => $b->id, 'role' => 'author'],
            ['author_id' => $a->id, 'role' => 'author'],
        ]);

        $this->assertSame([[$b->id, 'author', 0], [$a->id, 'author', 1]], $this->pivotRows($book));
    }

    public function test_prazna_lista_uklanja_sve_autore_knjige(): void
    {
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $book->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->updateAuthors($book, []);

        $this->assertSame([], $this->pivotRows($book));
        $this->assertModelExists($author);
    }

    public function test_izmena_jedne_knjige_ne_dira_autore_druge(): void
    {
        $book = Book::factory()->create();
        $other = Book::factory()->create();
        $author = Author::factory()->create();
        $other->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->updateAuthors($book, [['author_id' => $author->id, 'role' => 'author']]);

        $this->assertSame([[$author->id, 'author', 0]], $this->pivotRows($other));
        $this->assertSame([[$author->id, 'author', 0]], $this->pivotRows($book));
        $this->assertDatabaseCount('author_book', 2);
    }
}
