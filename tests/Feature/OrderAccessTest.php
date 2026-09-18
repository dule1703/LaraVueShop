<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Faza 0, korak 3:
 *   - /checkout više ne prosleđuje tuđu (poslednju) porudžbinu kao Inertia prop
 *   - /order/success/{order}, /order/cod-success/{order}, /payment/failed/{order}
 *     proveravaju vlasništvo (OrderPolicy@view): ulogovan korisnik -> user_id,
 *     gost -> order_id upisan u sesiju pri kreiranju porudžbine (OrderController::store)
 *   - neovlašćen pristup vraća 403, ne 404 (404 bi otkrio da ID postoji)
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(?User $user = null): Order
    {
        return Order::create([
            'user_id' => $user?->id,
            'first_name' => 'Pera',
            'last_name' => 'Perić',
            'customer_email' => 'pera@example.com',
            'total_price' => 19.99,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
    }

    public static function ownerViewRoutes(): array
    {
        return [
            'order.success' => ['order.success'],
            'order.cod.success' => ['order.cod.success'],
            'payment.failed' => ['payment.failed'],
        ];
    }

    #[DataProvider('ownerViewRoutes')]
    public function test_vlasnik_naloga_vidi_svoju_porudzbinu(string $routeName): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $this->actingAs($owner)
            ->get(route($routeName, $order))
            ->assertOk();
    }

    #[DataProvider('ownerViewRoutes')]
    public function test_drugi_ulogovan_korisnik_ne_vidi_tudju_porudzbinu(string $routeName): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route($routeName, $order))
            ->assertForbidden();
    }

    #[DataProvider('ownerViewRoutes')]
    public function test_gost_bez_odgovarajuce_sesije_ne_vidi_tudju_porudzbinu(string $routeName): void
    {
        $this->withoutVite();

        $order = $this->makeOrder();

        $this->get(route($routeName, $order))
            ->assertForbidden();
    }

    #[DataProvider('ownerViewRoutes')]
    public function test_gost_sa_sesijom_svoje_porudzbine_je_vidi(string $routeName): void
    {
        $this->withoutVite();

        $order = $this->makeOrder();

        $this->withSession(['guest_order_ids' => [$order->id]])
            ->get(route($routeName, $order))
            ->assertOk();
    }

    public function test_gost_odmah_posle_checkouta_i_dalje_vidi_svoju_cod_success_stranicu(): void
    {
        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 12.50,
            'stock' => 5,
        ]);

        $payload = [
            'first_name' => 'Gost',
            'last_name' => 'Kupac',
            'address' => 'Bulevar oslobođenja 1',
            'email' => 'gost@example.com',
            'city' => 'Novi Sad',
            'postal_code' => '21000',
            'phone' => '0601234567',
            'notes' => null,
            'items' => [
                ['id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cod',
        ];

        $response = $this->postJson('/orders', $payload);
        $response->assertRedirect();

        // Ista (test) sesija se nosi kroz naredni zahtev u istom testu.
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_gost_ne_vidi_tudju_porudzbinu_posle_sopstvenog_checkouta(): void
    {
        $this->withoutVite();

        $tudja = $this->makeOrder();

        $category = Category::factory()->active()->create();
        $product = Product::factory()->for($category)->create([
            'price' => 12.50,
            'stock' => 5,
        ]);

        $payload = [
            'first_name' => 'Gost',
            'last_name' => 'Kupac',
            'address' => 'Bulevar oslobođenja 1',
            'email' => 'gost@example.com',
            'city' => 'Novi Sad',
            'postal_code' => '21000',
            'phone' => '0601234567',
            'notes' => null,
            'items' => [
                ['id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cod',
        ];

        $this->postJson('/orders', $payload)->assertRedirect();

        // Sesija sada sadrži ID sopstvene porudžbine, ne i tuđe.
        $this->get(route('order.cod.success', $tudja))->assertForbidden();
    }

    public function test_checkout_stranica_ne_prosledjuje_tudju_porudzbinu(): void
    {
        $this->withoutVite();

        // Neka postoji nečija porudžbina — pre izmene, /checkout bi je procurio
        // svakom posetiocu kao Order::latest()->first().
        $this->makeOrder();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout')
                ->missing('order')
            );
    }

    /**
     * Samo IDOR provera na nivou rute/middleware-a. Ne testiramo da vlasnik
     * uspešno prođe kroz PayPalController::cancel — konstruktor tog kontrolera
     * odmah zove PayPal getAccessToken() (mrežni poziv, poznati problem #7 iz
     * CLAUDE.md), pa se ne može pokrenuti u testovima bez pravih/mock kredencijala.
     * To je odvojen problem od IDOR-a koji ovaj korak popravlja.
     */
    public function test_paypal_cancel_odbija_neovlascenog_korisnika(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('paypal.cancel', $order))
            ->assertForbidden();

        $guestOrder = $this->makeOrder();

        $this->get(route('paypal.cancel', $guestOrder))
            ->assertForbidden();
    }
}
