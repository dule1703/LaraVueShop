<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Doubles\FakePaymentGateway;
use Tests\TestCase;

/**
 * Faza 0, korak 4:
 *   - problem #7: PayPalController sada prima PaymentGateway kroz DI umesto
 *     da ga konstruiše sam (što je ranije značilo trenutni mrežni poziv na
 *     getAccessToken() u konstruktoru). Ovde se bind-uje FakePaymentGateway
 *     — ni jedan test u ovom fajlu ne pravi mrežni poziv ka PayPal-u.
 *   - novootkriveni IDOR: /paypal/success/{order} i
 *     /paypal/create-payment/{order} sada takođe idu kroz can:view,order
 *     (isti obrazac kao ostale order rute, OrderPolicy@view).
 */
class PayPalPaymentTest extends TestCase
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
            'payment_method' => 'paypal',
        ]);
    }

    public function test_create_payment_preusmerava_na_approval_link(): void
    {
        $order = $this->makeOrder();

        $fake = new FakePaymentGateway(createOrderResponse: [
            'id' => 'PAYPAL-ORDER-1',
            'links' => [
                ['rel' => 'approve', 'href' => 'https://paypal.example.com/approve/1'],
            ],
        ]);
        $this->app->instance(PaymentGateway::class, $fake);

        $this->withSession(['guest_order_ids' => [$order->id]])
            ->get(route('paypal.createPayment', $order))
            ->assertRedirect('https://paypal.example.com/approve/1');

        $this->assertSame([$order->id], $fake->createOrderCalls);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL-ORDER-1',
            'status' => 'pending',
        ]);
    }

    public function test_success_potvrdjuje_placanje_i_oznacava_porudzbinu_kao_placenu(): void
    {
        $order = $this->makeOrder();
        $order->payment()->create([
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL-ORDER-1',
            'amount' => $order->total_price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $fake = new FakePaymentGateway(captureOrderResponse: ['status' => 'COMPLETED']);
        $this->app->instance(PaymentGateway::class, $fake);

        $this->withSession(['guest_order_ids' => [$order->id]])
            ->get(route('paypal.success', $order) . '?token=PAYPAL-ORDER-1')
            ->assertRedirect(route('order.success', $order));

        $this->assertSame(['PAYPAL-ORDER-1'], $fake->captureOrderCalls);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_success_bez_tokena_vraca_na_checkout_bez_pozivanja_gateway_a(): void
    {
        $order = $this->makeOrder();

        $fake = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $fake);

        $this->withSession(['guest_order_ids' => [$order->id]])
            ->get(route('paypal.success', $order))
            ->assertRedirect(route('checkout'));

        $this->assertSame([], $fake->captureOrderCalls);
    }

    public function test_vlasnik_moze_da_otkaze_svoje_placanje_bez_mreznog_poziva(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $order->payment()->create([
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL-ORDER-1',
            'amount' => $order->total_price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $this->app->instance(PaymentGateway::class, new FakePaymentGateway());

        $this->actingAs($owner)
            ->get(route('paypal.cancel', $order))
            ->assertRedirect(route('checkout'));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'cancelled']);
    }

    /**
     * Faza 5: otkazivanje vraća zalihu i upisuje stock_movements red (reason = 'cancel').
     */
    public function test_otkazivanje_vraca_zalihu_i_upisuje_stock_movement(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $product = Product::factory()->create(['stock' => 3]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 2,
        ]);

        $this->app->instance(PaymentGateway::class, new FakePaymentGateway());

        $this->actingAs($owner)
            ->get(route('paypal.cancel', $order))
            ->assertRedirect(route('checkout'));

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => 2,
            'reason' => 'cancel',
            'order_id' => $order->id,
            'user_id' => $owner->id,
        ]);
    }

    /**
     * Dva paralelna zahteva na istu porudžbinu ne smeju duplo da vrate zalihu
     * — guard u InventoryService::restoreStock preskače porudžbinu koja je
     * već u terminalnom stanju (isti princip kao WHERE stock >= :q iz Faze 0).
     */
    public function test_dvostruko_otkazivanje_ne_duplira_povrat_zaliha(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $product = Product::factory()->create(['stock' => 3]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 2,
        ]);

        $this->app->instance(PaymentGateway::class, new FakePaymentGateway());

        $this->actingAs($owner)->get(route('paypal.cancel', $order));
        $this->actingAs($owner)->get(route('paypal.cancel', $order));

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    /**
     * Faza 5: kad capture ne uspe (status != COMPLETED / gateway baci grešku),
     * zaliha se vraća i upisuje se stock_movements red (reason = 'payment_failed').
     */
    public function test_neuspelo_placanje_vraca_zalihu_i_upisuje_stock_movement(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $order->payment()->create([
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL-ORDER-1',
            'amount' => $order->total_price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);
        $product = Product::factory()->create(['stock' => 3]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
        ]);

        $fake = new FakePaymentGateway(captureOrderResponse: ['status' => 'DECLINED']);
        $this->app->instance(PaymentGateway::class, $fake);

        $this->actingAs($owner)
            ->get(route('paypal.success', $order) . '?token=PAYPAL-ORDER-1')
            ->assertRedirect(route('payment.failed', $order));

        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'failed']);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => 1,
            'reason' => 'payment_failed',
            'order_id' => $order->id,
            'user_id' => $owner->id,
        ]);
    }

    public static function ownerGatewayRoutes(): array
    {
        return [
            'paypal.success' => ['paypal.success'],
            'paypal.createPayment' => ['paypal.createPayment'],
        ];
    }

    private function gatewayRouteUrl(string $routeName, Order $order): string
    {
        $url = route($routeName, $order);

        // success() zahteva ?token=... da bi uopšte pokušao capture; ovde nas
        // zanima samo da autorizacija propusti vlasnika do kontrolera.
        return $routeName === 'paypal.success' ? $url . '?token=PAYPAL-ORDER-1' : $url;
    }

    #[DataProvider('ownerGatewayRoutes')]
    public function test_vlasnik_prolazi_autorizaciju_na_paypal_gateway_rutama(string $routeName): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $order->payment()->create([
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL-ORDER-1',
            'amount' => $order->total_price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $fake = new FakePaymentGateway(
            createOrderResponse: [
                'id' => 'PAYPAL-ORDER-1',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.example.com/approve/1']],
            ],
            captureOrderResponse: ['status' => 'COMPLETED'],
        );
        $this->app->instance(PaymentGateway::class, $fake);

        $response = $this->actingAs($owner)
            ->get($this->gatewayRouteUrl($routeName, $order));

        // Autorizacija je propustila zahtev do kontrolera (nije 403) — sama
        // poslovna logika dalje redirect-uje na approval link / order.success.
        $response->assertStatus(302);
        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[DataProvider('ownerGatewayRoutes')]
    public function test_drugi_ulogovan_korisnik_ne_prolazi_autorizaciju_na_paypal_gateway_rutama(string $routeName): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder($owner);
        $other = User::factory()->create();

        $fake = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $fake);

        $this->actingAs($other)
            ->get($this->gatewayRouteUrl($routeName, $order))
            ->assertForbidden();

        $this->assertSame([], $fake->createOrderCalls);
        $this->assertSame([], $fake->captureOrderCalls);
    }

    #[DataProvider('ownerGatewayRoutes')]
    public function test_gost_bez_odgovarajuce_sesije_ne_prolazi_autorizaciju_na_paypal_gateway_rutama(string $routeName): void
    {
        $order = $this->makeOrder();

        $fake = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $fake);

        $this->get($this->gatewayRouteUrl($routeName, $order))
            ->assertForbidden();

        $this->assertSame([], $fake->createOrderCalls);
        $this->assertSame([], $fake->captureOrderCalls);
    }
}
