<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Prikaži korpu trenutnog korisnika
     * GET /api/cart
     */
    public function show()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $cart = $user->cart;
            
            // ✅ Ako nema korpe, vrati praznu strukturu
            if (!$cart) {
                return response()->json([
                    'cart' => [
                        'id'         => null,
                        'user_id'    => $user->id,
                        'items'      => [],  
                        'updated_at' => null,
                    ]
                ]);
            }

            // ✅ Osiguraj da items bude array (čak i ako je null u bazi)
            $items = is_array($cart->items) ? $cart->items : [];

            return response()->json([
                'cart' => [
                    'id'         => $cart->id,
                    'user_id'    => $cart->user_id,
                    'items'      => $items,
                    'updated_at' => $cart->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[CART API] Show error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to load cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sinhronizuj korpu sa frontend-om
     * POST /api/cart/sync
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'items' => 'present|array',
        ]);

        $items = $validated['items'];

        // Uvek ažuriraj ili kreiraj zapis – NIKADA NE BRIŠI
        $cart = $user->cart;

        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $user->id,
                'items'   => $items,
            ]);
        } else {
            $cart->items = $items;  // ← OVO JE KLJUČNO – uvek ažurira, čak i ako je prazno []
            $cart->save();
        }

        Log::info('Cart synced for user ' . $user->id, [
            'item_count' => count($items),
            'items'      => $items,
        ]);

        return response()->json([
            'success' => true,
            'cart' => $cart,
        ]);
    }
}