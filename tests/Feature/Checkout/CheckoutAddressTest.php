<?php

namespace Tests\Feature\Checkout;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Adresa isporuke pri checkout-u (OrderController::store):
 *   - address_id (sačuvana adresa ulogovanog korisnika) ILI inline shipping.* polja
 *   - gost nikad ne sme da pošalje address_id; tuđa adresa izgleda isto kao nepostojeća
 *   - orders.shipping_* je snapshot — kasnija izmena/brisanje adrese ga ne menja
 *   - legacy kolone (first_name/last_name/address/...) se i dalje pune (admin prikaz)
 */
class CheckoutAddressTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $category = Category::factory()->active()->create();
        $this->product = Product::factory()->for($category)->create([
            'price' => 10.00,
            'stock' => 10,
        ]);
    }

    private function shipping(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Mika Mikić',
            'phone' => '0611111111',
            'line1' => 'Zmaj Jovina 3',
            'line2' => 'stan 4',
            'city' => 'Novi Sad',
            'postal_code' => '21000',
            'country' => 'Srbija',
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'kupac@example.com',
            'notes' => null,
            'items' => [['id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cod',
        ], $overrides);
    }

    private function savedAddress(User $user, array $overrides = []): Address
    {
        return app(AddressService::class)->create($user, array_merge([
            'recipient_name' => 'Jovan Petar Jovanović',
            'phone' => '0622222222',
            'line1' => 'Knez Mihailova 10',
            'line2' => null,
            'city' => 'Beograd',
            'postal_code' => '11000',
            'country' => 'Srbija',
        ], $overrides));
    }

    public function test_porudzbina_sa_sacuvanom_adresom_kopira_snapshot(): void
    {
        $user = User::factory()->create();
        $address = $this->savedAddress($user);

        $this->actingAs($user)
            ->postJson('/orders', $this->payload(['address_id' => $address->id]))
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('Jovan Petar Jovanović', $order->shipping_recipient_name);
        $this->assertSame('0622222222', $order->shipping_phone);
        $this->assertSame('Knez Mihailova 10', $order->shipping_line1);
        $this->assertNull($order->shipping_line2);
        $this->assertSame('Beograd', $order->shipping_city);
        $this->assertSame('11000', $order->shipping_postal_code);
        $this->assertSame('Srbija', $order->shipping_country);
        $this->assertSame($address->id, $order->shipping_address_id);
    }

    public function test_inline_adresa_bez_cuvanja(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/orders', $this->payload(['shipping' => $this->shipping()]))
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('Mika Mikić', $order->shipping_recipient_name);
        $this->assertSame('0611111111', $order->shipping_phone);
        $this->assertSame('Zmaj Jovina 3', $order->shipping_line1);
        $this->assertSame('stan 4', $order->shipping_line2);
        $this->assertSame('Novi Sad', $order->shipping_city);
        $this->assertSame('21000', $order->shipping_postal_code);
        $this->assertSame('Srbija', $order->shipping_country);
        $this->assertNull($order->shipping_address_id);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_inline_adresa_bez_drzave_dobija_srbija(): void
    {
        $this->postJson('/orders', $this->payload([
            'shipping' => $this->shipping(['country' => null]),
        ]))->assertRedirect();

        $this->assertSame('Srbija', Order::firstOrFail()->shipping_country);
    }

    public function test_inline_adresa_sa_save_address_kreira_podrazumevanu_adresu(): void
    {
        $user = User::factory()->create();
        // Već postoji podrazumevana adresa — nova sačuvana iz checkout-a je preuzima.
        $old = $this->savedAddress($user);

        $this->actingAs($user)
            ->postJson('/orders', $this->payload([
                'shipping' => $this->shipping(),
                'save_address' => true,
            ]))
            ->assertRedirect();

        $new = Address::where('user_id', $user->id)->where('line1', 'Zmaj Jovina 3')->firstOrFail();
        $this->assertTrue($new->is_default);
        $this->assertSame('Mika Mikić', $new->recipient_name);
        $this->assertSame('stan 4', $new->line2);
        $this->assertFalse($old->fresh()->is_default);

        $this->assertSame($new->id, Order::firstOrFail()->shipping_address_id);
    }

    public function test_save_address_se_ponistava_ako_porudzbina_padne(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/orders', $this->payload([
                'items' => [['id' => $this->product->id, 'quantity' => 999]],
                'shipping' => $this->shipping(),
                'save_address' => true,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_gost_sa_save_address_ne_kreira_adresu(): void
    {
        $this->postJson('/orders', $this->payload([
            'shipping' => $this->shipping(),
            'save_address' => true,
        ]))->assertRedirect();

        $this->assertDatabaseCount('addresses', 0);
        $this->assertNull(Order::firstOrFail()->shipping_address_id);
    }

    public function test_tudja_adresa_vraca_422_i_ne_kreira_porudzbinu(): void
    {
        $owner = User::factory()->create();
        $foreign = $this->savedAddress($owner);

        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->postJson('/orders', $this->payload(['address_id' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_id' => 'Adresa ne postoji ili ne pripada vašem nalogu.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_nepostojeca_adresa_daje_istu_poruku_kao_tudja(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/orders', $this->payload(['address_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_id' => 'Adresa ne postoji ili ne pripada vašem nalogu.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_gost_ne_sme_da_posalje_address_id(): void
    {
        // Prava, postojeća adresa nekog korisnika — gost je i dalje ne sme referencirati.
        $address = $this->savedAddress(User::factory()->create());

        $this->postJson('/orders', $this->payload([
            'address_id' => $address->id,
            'shipping' => $this->shipping(),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_id' => 'Adresa je dostupna samo za ulogovane korisnike.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_bez_address_id_shipping_polja_su_obavezna(): void
    {
        $this->postJson('/orders', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping.recipient_name',
                'shipping.phone',
                'shipping.line1',
                'shipping.city',
                'shipping.postal_code',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_izmena_i_brisanje_adrese_ne_menja_snapshot_porudzbine(): void
    {
        $user = User::factory()->create();
        $address = $this->savedAddress($user);

        $this->actingAs($user)
            ->postJson('/orders', $this->payload(['address_id' => $address->id]))
            ->assertRedirect();

        $orderId = Order::firstOrFail()->id;

        $expected = [
            'shipping_recipient_name' => 'Jovan Petar Jovanović',
            'shipping_phone' => '0622222222',
            'shipping_line1' => 'Knez Mihailova 10',
            'shipping_line2' => null,
            'shipping_city' => 'Beograd',
            'shipping_postal_code' => '11000',
            'shipping_country' => 'Srbija',
        ];

        // 1) Izmena sačuvane adrese posle porudžbine.
        app(AddressService::class)->update($address, [
            'recipient_name' => 'Neko Drugi',
            'phone' => '0699999999',
            'line1' => 'Potpuno druga 99',
            'line2' => 'sprat 7',
            'city' => 'Niš',
            'postal_code' => '18000',
            'country' => 'Crna Gora',
        ]);

        $this->assertSame($expected, Order::findOrFail($orderId)->only(array_keys($expected)));

        // 2) Brisanje sačuvane adrese posle porudžbine — snapshot ostaje,
        //    samo audit FK (shipping_address_id) pada na NULL (nullOnDelete).
        app(AddressService::class)->delete($address->fresh());

        $order = Order::findOrFail($orderId);
        $this->assertSame($expected, $order->only(array_keys($expected)));
        $this->assertNull($order->shipping_address_id);
    }

    public function test_legacy_kolone_za_admin_prikaz_su_i_dalje_popunjene(): void
    {
        $user = User::factory()->create();
        $address = $this->savedAddress($user);

        $this->actingAs($user)
            ->postJson('/orders', $this->payload(['address_id' => $address->id]))
            ->assertRedirect();

        $order = Order::firstOrFail();
        // Naivan split na prvi razmak (dokumentovano pojednostavljenje).
        $this->assertSame('Jovan', $order->first_name);
        $this->assertSame('Petar Jovanović', $order->last_name);
        $this->assertSame('Knez Mihailova 10', $order->address);
        $this->assertSame('Beograd', $order->city);
        $this->assertSame('11000', $order->postal_code);
        $this->assertSame('0622222222', $order->phone);
    }

    public function test_legacy_address_spaja_line1_i_line2_a_jednodelno_ime_ima_prazno_prezime(): void
    {
        $this->postJson('/orders', $this->payload([
            'shipping' => $this->shipping(['recipient_name' => 'Madona']),
        ]))->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('Madona', $order->first_name);
        $this->assertSame('', $order->last_name);
        $this->assertSame('Zmaj Jovina 3, stan 4', $order->address);
        $this->assertSame('Novi Sad', $order->city);
        $this->assertSame('21000', $order->postal_code);
        $this->assertSame('0611111111', $order->phone);
    }

    public function test_checkout_stranica_prosledjuje_sopstvene_adrese_podrazumevana_prva(): void
    {
        $user = User::factory()->create();
        $default = $this->savedAddress($user, ['line1' => 'Prva 1']);
        $second = $this->savedAddress($user, ['line1' => 'Druga 2']);
        $this->savedAddress(User::factory()->create());

        $this->actingAs($user)
            ->get(route('checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout')
                ->has('addresses', 2)
                ->where('addresses.0.id', $default->id)
                ->where('addresses.1.id', $second->id)
            );
    }

    public function test_checkout_stranica_gost_dobija_prazne_adrese(): void
    {
        $this->savedAddress(User::factory()->create());

        $this->get(route('checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout')
                ->has('addresses', 0)
            );
    }
}
