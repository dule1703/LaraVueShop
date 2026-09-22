<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 0, korak 2:
 *   - cena i total_price se računaju na serveru iz products.price, klijent ih ne kontroliše
 *   - zalihe se umanjuju atomski (UPDATE ... WHERE stock >= :q), nikad ispod nule
 *   - kad nema dovoljno zaliha, porudžbina se ne kreira i stock ostaje netaknut
 */
class OrderStoreTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'first_name'   => 'Pera',
            'last_name'    => 'Perić',
            'address'      => 'Bulevar oslobođenja 1',
            'email'        => 'pera@example.com',
            'city'         => 'Novi Sad',
            'postal_code'  => '21000',
            'phone'        => '0601234567',
            'notes'        => null,
            'items'        => $items,
            'payment_method' => 'cod',
        ], $overrides);
    }

    public function test_klijent_ne_moze_da_izmeni_cenu(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 20.00,
            'stock' => 5,
        ]);

        $payload = $this->checkoutPayload([
            [
                // Klijent šalje lažnu (izmenjenu) cenu i naziv — server ih ignoriše.
                'id'       => $product->id,
                'quantity' => 2,
                'price'    => 0.01,
                'name'     => 'Izmenjen naziv',
            ],
        ], [
            'total_price' => 0.02,
        ]);

        $response = $this->postJson('/orders', $payload);

        $response->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertEquals(40.00, (float) $order->total_price);

        $orderItem = $order->items()->firstOrFail();
        $this->assertEquals(20.00, (float) $orderItem->product_price);
        $this->assertEquals($product->name, $orderItem->product_name);
    }

    public function test_dovoljno_zaliha_porudzbina_uspeva_i_stock_opada(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 15.50,
            'stock' => 10,
        ]);

        $payload = $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 3],
        ]);

        $response = $this->postJson('/orders', $payload);

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(7, $product->fresh()->stock);
    }

    /**
     * Faza 5: svaka stavka porudžbine upisuje tačan stock_movements red
     * (delta = -quantity, reason = 'order', order_id postavljen) u istoj
     * transakciji kao i umanjenje zaliha.
     */
    public function test_porudzbina_upisuje_stock_movements_redove(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $productA = Product::factory()->for($category)->create(['price' => 10, 'stock' => 5]);
        $productB = Product::factory()->for($category)->create(['price' => 20, 'stock' => 5]);

        $payload = $this->checkoutPayload([
            ['id' => $productA->id, 'quantity' => 2],
            ['id' => $productB->id, 'quantity' => 1],
        ]);

        $response = $this->postJson('/orders', $payload);
        $response->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'delta' => -2,
            'reason' => 'order',
            'order_id' => $order->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'delta' => -1,
            'reason' => 'order',
            'order_id' => $order->id,
            'user_id' => null,
        ]);
    }

    public function test_porudzbina_ulogovanog_korisnika_upisuje_user_id_u_stock_movements(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create(['price' => 10, 'stock' => 5]);

        $payload = $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 1],
        ], ['email' => $user->email]);

        $response = $this->actingAs($user)->postJson('/orders', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => -1,
            'reason' => 'order',
            'user_id' => $user->id,
        ]);
    }

    public function test_nedovoljno_zaliha_vraca_4xx_i_ne_menja_bazu(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 15.50,
            'stock' => 2,
        ]);

        $payload = $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 5],
        ]);

        $response = $this->postJson('/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertEquals(2, $product->fresh()->stock);
    }

    /**
     * Prava paralelnost (dva istovremena HTTP zahteva na dve konekcije) se ne može
     * realno testirati ovde: PHPUnit izvršava zahteve sinhrono u jednom procesu, a
     * testovi rade nad SQLite ":memory:" bazom koja postoji samo unutar jedne
     * konekcije — nema drugog "worker-a" koji bi stigao istovremeno.
     *
     * Umesto toga, test simulira ishod race-a serijski: prva porudžbina potroši
     * poslednji primerak, druga (koja izgleda kao da je "istovremena" jer obe
     * kreću od istog početnog stanja stock=1) mora da padne na istom atomskom
     * `UPDATE ... WHERE stock >= :q` guard-u koji bi zaustavio i pravu trku na
     * MariaDB-u sa dve stvarne konekcije (tamo red-level lock nad tim redom
     * serijalizuje ta dva UPDATE-a — tačno isti mehanizam, samo ovde bez threada).
     */
    public function test_dve_porudzbine_za_poslednji_primerak_samo_jedna_uspe(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 9.99,
            'stock' => 1,
        ]);

        $payload = $this->checkoutPayload([
            ['id' => $product->id, 'quantity' => 1],
        ]);

        $first = $this->postJson('/orders', $payload);
        $second = $this->postJson('/orders', $payload);

        $first->assertRedirect();
        $second->assertStatus(422);

        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(0, $product->fresh()->stock);
    }
}
