<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Faza 1: migracije za data model knjiga prolaze i daju očekivanu šemu.
 */
class BookSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_nove_tabele_postoje_sa_svim_kolonama(): void
    {
        $this->assertTrue(Schema::hasColumns('authors', [
            'id', 'name', 'slug', 'bio', 'photo', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('publishers', [
            'id', 'name', 'slug', 'website', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('books', [
            'id', 'product_id', 'isbn13', 'isbn10', 'publisher_id', 'subtitle',
            'original_title', 'published_year', 'pages', 'language', 'script',
            'format', 'weight_g', 'search_text', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('author_book', [
            'book_id', 'author_id', 'role', 'position',
        ]));

        $this->assertTrue(Schema::hasColumns('stock_movements', [
            'id', 'product_id', 'delta', 'reason', 'order_id', 'user_id', 'note', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('stock_movements', 'updated_at'));
    }

    public function test_categories_ima_parent_id_i_position(): void
    {
        $this->assertTrue(Schema::hasColumns('categories', ['parent_id', 'position']));
    }

    public function test_author_book_ima_kompozitni_primarni_kljuc(): void
    {
        $primary = collect(Schema::getIndexes('author_book'))->firstWhere('primary', true);

        $this->assertNotNull($primary);
        $this->assertSame(['book_id', 'author_id', 'role'], $primary['columns']);
    }

    public function test_products_stock_je_nullable_sa_defaultom_nula(): void
    {
        $stock = collect(Schema::getColumns('products'))->firstWhere('name', 'stock');

        $this->assertTrue($stock['nullable']);
        $this->assertSame('0', trim((string) $stock['default'], "'"));
    }

    public function test_proizvod_moze_imati_neogranicene_zalihe(): void
    {
        $product = Product::factory()->unlimitedStock()->create();

        $this->assertNull($product->fresh()->stock);
    }

    public function test_proizvod_bez_navedenih_zaliha_dobija_nulu_a_ne_null(): void
    {
        $product = Product::factory()->make();
        $attributes = $product->getAttributes();
        unset($attributes['stock']);

        $id = DB::table('products')->insertGetId(array_merge($attributes, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $stock = DB::table('products')->where('id', $id)->value('stock');
        $this->assertNotNull($stock);
        $this->assertSame(0, (int) $stock);
    }
}
