<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Faza 5: sve promene zaliha (izlaz kod porudžbine, povrat kod otkazivanja/
 * neuspelog plaćanja, ručna dopuna) idu isključivo kroz ovaj servis, uvek uz
 * odgovarajući stock_movements red u istoj transakciji.
 */
class InventoryService
{
    /**
     * Vraća zalihu porudžbine koja nije uspela (otkazano plaćanje ili neuspelo
     * plaćanje), upisuje stock_movements red po stavci i postavlja finalni
     * status porudžbine — sve u jednoj transakciji.
     *
     * Idempotentno: zaključava red porudžbine (lockForUpdate) i preskače povrat
     * ako je porudžbina već u terminalnom stanju (cancelled/failed) — sprečava
     * dupli povrat pri dva paralelna zahteva na istu porudžbinu. Status se MORA
     * postaviti unutar iste zaključane transakcije (ne posle, u pozivaocu) —
     * inače bi prozor između povrata zaliha i upisa statusa ostavio isti
     * race koji bi drugi paralelni zahtev video kao "još nije otkazano" i
     * dupliraro povrat (isti princip row-level lock-a kao WHERE stock >= :q
     * guard iz Faze 0, samo nad `orders` redom umesto `products` redom).
     */
    public function restoreStock(Order $order, string $reason, string $newStatus): void
    {
        DB::transaction(function () use ($order, $reason, $newStatus) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (in_array($locked->status, ['cancelled', 'failed'], true)) {
                return;
            }

            foreach ($order->items as $item) {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'delta' => $item->quantity,
                    'reason' => $reason,
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                ]);
            }

            $locked->update(['status' => $newStatus]);
        });
    }

    /**
     * Ručna dopuna zaliha od strane admina (npr. nova pošiljka od izdavača).
     * `stock === null` (e-knjiga, neograničena zaliha) se ne dopunjava —
     * poziv se ignoriše na kontroler nivou (vidi BookController::restock).
     */
    public function restock(Product $product, int $quantity, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($product, $quantity, $admin, $note) {
            Product::whereKey($product->id)->increment('stock', $quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'delta' => $quantity,
                'reason' => 'manual',
                'user_id' => $admin->id,
                'note' => $note,
            ]);
        });
    }
}
