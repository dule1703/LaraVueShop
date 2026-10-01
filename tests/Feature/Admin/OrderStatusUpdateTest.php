<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Housekeeping (posle Admin panel koraka 4): ručno otkazivanje porudžbine
 * preko admin panela ranije NIJE vraćalo zalihu (samo `$order->update()`),
 * za razliku od PayPal cancel/fail toka (`InventoryService::restoreStock`,
 * Faza 5, vidi `tests/Feature/PayPalPaymentTest.php`). Isti mehanizam se
 * sad koristi i ovde — testovi ovde kopiraju strukturu PayPal testova,
 * `reason = 'admin_cancel'` (ne 'cancel', da se razlikuje trigger u
 * `stock_movements` istoriji od PayPal buyer-cancel toka).
 */
class OrderStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeOrderWithItem(Product $product, int $quantity): Order
    {
        $order = Order::create([
            'first_name' => 'Pera',
            'last_name' => 'Perić',
            'customer_email' => 'pera@example.com',
            'total_price' => 19.99,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    public function test_admin_otkazivanje_vraca_zalihu_i_upisuje_stock_movement(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->makeOrderWithItem($product, 2);

        $this->actingAs($this->admin())
            ->from(route('admin.orders.show', $order))
            ->put(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'delta' => 2,
            'reason' => 'admin_cancel',
            'order_id' => $order->id,
        ]);
    }

    /**
     * Isti guard kao PayPal cancel/fail (InventoryService::restoreStock) —
     * dva zahteva na istu već-otkazanu porudžbinu ne smeju duplo vratiti
     * zalihu.
     */
    public function test_dvostruko_otkazivanje_ne_duplira_povrat_zaliha(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->makeOrderWithItem($product, 2);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.orders.update', $order), ['status' => 'cancelled']);
        $this->actingAs($admin)->put(route('admin.orders.update', $order), ['status' => 'cancelled']);

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    /**
     * Svi ostali statusi (shipped, paid, processing, delivered...) ostaju
     * plain update — restoreStock se poziva ISKLJUČIVO za 'cancelled'.
     */
    public function test_ostali_statusi_ne_diraju_zalihu(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->makeOrderWithItem($product, 2);

        $this->actingAs($this->admin())
            ->put(route('admin.orders.update', $order), ['status' => 'shipped'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
