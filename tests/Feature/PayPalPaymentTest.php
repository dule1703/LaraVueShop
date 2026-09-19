<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Order;
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
