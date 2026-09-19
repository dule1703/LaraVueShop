<?php

namespace Tests\Feature\Catalog;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 1: samo tabela i model stock_movements. Logika upisa je Faza 5.
 */
class StockMovementTest extends TestCase
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

    public function test_factory_pravi_validan_zapis_sa_created_at_bez_updated_at(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertInstanceOf(Product::class, $movement->product);
        $this->assertContains($movement->reason, StockMovement::REASONS);
        $this->assertNotNull($movement->fresh()->created_at);
        $this->assertArrayNotHasKey('updated_at', $movement->fresh()->getAttributes());
    }

    public function test_delta_moze_biti_negativna_i_veze_rade(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $product = Product::factory()->create();

        $movement = StockMovement::factory()->for($product)->create([
            'delta' => -3,
            'reason' => 'order',
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $movement = $movement->fresh();
        $this->assertSame(-3, $movement->delta);
        $this->assertTrue($movement->order->is($order));
        $this->assertTrue($movement->user->is($user));
        $this->assertTrue($product->stockMovements->first()->is($movement));
    }

    public function test_brisanje_korisnika_i_porudzbine_ne_brise_zapis(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $movement = StockMovement::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $order->delete();
        $user->delete();

        $movement = $movement->fresh();
        $this->assertNotNull($movement);
        $this->assertNull($movement->order_id);
        $this->assertNull($movement->user_id);
    }

    public function test_proizvod_sa_istorijom_zaliha_ne_moze_da_se_obrise(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(QueryException::class);
        $movement->product->delete();
    }
}
