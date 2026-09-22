<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cart refaktor: korpa čuva samo {product_id, quantity}, cena/naziv/slika se
 * uvek čitaju sa servera preko ovog endpoint-a - nikad iz onoga što je
 * frontend cart ranije sačuvao.
 */
class CartProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gost_moze_da_dobije_podatke_o_proizvodima(): void
    {
        $product = Product::factory()->create(['price' => 19.99]);

        $response = $this->getJson('/api/cart/products?' . http_build_query(['ids' => [$product->id]]));

        $response->assertOk();
        $products = $response->json('products');

        $this->assertCount(1, $products);
        $this->assertSame($product->id, $products[0]['id']);
        $this->assertSame($product->name, $products[0]['name']);
        $this->assertEquals(19.99, (float) $products[0]['price']);
        $this->assertSame($product->image, $products[0]['image']);
        $this->assertSame($product->stock, $products[0]['stock']);
        $this->assertSame($product->stock > 0, $products[0]['available']);
    }

    public function test_nepostojeci_product_id_se_tiho_izostavlja(): void
    {
        $product = Product::factory()->create();
        $nonExistentId = $product->id + 999;

        $response = $this->getJson('/api/cart/products?' . http_build_query([
            'ids' => [$product->id, $nonExistentId],
        ]));

        $response->assertOk();
        $ids = collect($response->json('products'))->pluck('id');

        $this->assertTrue($ids->contains($product->id));
        $this->assertFalse($ids->contains($nonExistentId));
        $this->assertCount(1, $ids);
    }

    public function test_neaktivan_proizvod_se_tiho_izostavlja(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $response = $this->getJson('/api/cart/products?' . http_build_query(['ids' => [$inactive->id]]));

        $response->assertOk();
        $response->assertJson(['products' => []]);
    }

    public function test_stock_null_znaci_dostupno(): void
    {
        $ebook = Product::factory()->unlimitedStock()->create();

        $response = $this->getJson('/api/cart/products?' . http_build_query(['ids' => [$ebook->id]]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $ebook->id, 'stock' => null, 'available' => true]);
    }

    public function test_ids_je_obavezan_parametar(): void
    {
        $response = $this->getJson('/api/cart/products');

        $response->assertStatus(422);
    }
}
