<?php

namespace Tests\Feature\Console;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 4: catalog:reindex-search-text — backfill za knjige upisane pre nego
 * što je BookObserver počeo da puni search_text (ručno pokretanje, idempotentno).
 */
class ReindexSearchTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_popunjava_search_text_za_postojece_knjige(): void
    {
        $book = Book::factory()->create();
        $book->product->update(['name' => 'Simulacija stare knjige']);
        // Simulira red upisan pre Faze 4 (observer bi ga inače odmah popunio).
        Book::query()->whereKey($book->id)->update(['search_text' => null]);

        $this->assertNull($book->fresh()->search_text);

        $this->artisan('catalog:reindex-search-text')->assertSuccessful();

        $this->assertStringContainsString('simulacija stare knjige', $book->fresh()->search_text);
    }

    public function test_dry_run_ne_upisuje_nista(): void
    {
        $book = Book::factory()->create();
        Book::query()->whereKey($book->id)->update(['search_text' => null]);

        $this->artisan('catalog:reindex-search-text --dry-run')->assertSuccessful();

        $this->assertNull($book->fresh()->search_text);
    }

    public function test_idempotentno_ponovno_pokretanje_ne_menja_vec_tacan_search_text(): void
    {
        $book = Book::factory()->create();

        $this->artisan('catalog:reindex-search-text')->assertSuccessful();
        $after = $book->fresh()->search_text;

        $this->artisan('catalog:reindex-search-text')->assertSuccessful();

        $this->assertSame($after, $book->fresh()->search_text);
    }
}
