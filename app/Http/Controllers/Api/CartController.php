<?php
// app/Http/Controllers/Api/CartController.php

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

            // Ako korisnik nema korpu, kreiraj praznu
            $cart = $user->cart;
            
            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'items'   => [], // Array se automatski pretvara u JSON
                ]);
            }

            // items je već array zbog cast-a u modelu
            $items = $cart->items ?? [];

            Log::info('[CART API] Loaded for user ' . $user->id, [
                'item_count' => count($items),
                'items' => $items
            ]);

            return response()->json([
                'cart' => [
                    'id'         => $cart->id,
                    'user_id'    => $cart->user_id,
                    'items'      => $items, // Već je array
                    'updated_at' => $cart->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[CART API] Show error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
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
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Validacija
            $request->validate([
                'items' => 'required|array',
            ]);

            // Ako korisnik nema korpu, kreiraj je
            $cart = $user->cart;
            
            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'items'   => [],
                ]);
            }

            $items = $request->input('items', []);

            // Ažuriraj korpu - array se automatski konvertuje u JSON zbog cast-a
            $cart->update(['items' => $items]);

            Log::info('[CART API] Synced for user ' . $user->id, [
                'item_count' => count($items),
                'items' => $items
            ]);

            return response()->json([
                'success' => true,
                'cart' => [
                    'id'         => $cart->id,
                    'user_id'    => $cart->user_id,
                    'items'      => $cart->items, // Već je array zbog cast-a
                    'updated_at' => $cart->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[CART API] Sync error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to sync cart',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}