<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Napomena: cena i total_price se NAMERNO ne uzimaju od klijenta.
        // Server ih računa iz baze (products.price) u transakciji ispod.
        $validated = $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'address'      => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'city'         => 'required|string|max:255',
            'postal_code'  => 'required|string|max:20',
            'phone'        => 'required|string|max:50',
            'notes'        => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:paypal,cod',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $totalPrice = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Jedna od knjiga iz korpe više ne postoji.',
                    ]);
                }

                // Atomsko umanjenje zaliha — uspeva samo ako ima dovoljno stanja.
                $affected = Product::where('id', $product->id)
                    ->where('stock', '>=', $item['quantity'])
                    ->update(['stock' => DB::raw('stock - ' . (int) $item['quantity'])]);

                if ($affected === 0) {
                    $currentStock = Product::where('id', $product->id)->value('stock') ?? 0;

                    throw ValidationException::withMessages([
                        'items' => "Nema dovoljno zaliha za knjigu \"{$product->name}\" (na stanju: {$currentStock}, traženo: {$item['quantity']}).",
                    ]);
                }

                $orderItems[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_price' => $product->price,
                    'quantity'      => $item['quantity'],
                ];

                $totalPrice += $product->price * $item['quantity'];
            }

            $order = Order::create([
                'user_id'         => Auth::id(),
                'first_name'      => $validated['first_name'],
                'last_name'       => $validated['last_name'],
                'address'         => $validated['address'],
                'city'            => $validated['city'],
                'postal_code'     => $validated['postal_code'],
                'phone'           => $validated['phone'],
                'notes'           => $validated['notes'] ?? null,
                'total_price'     => $totalPrice,
                'status'          => 'pending',
                'payment_method'  => $validated['payment_method'],
                'customer_email'  => Auth::check() ? Auth::user()->email : $validated['email'],
            ]);

            foreach ($orderItems as $orderItem) {
                OrderItem::create($orderItem + ['order_id' => $order->id]);

                StockMovement::create([
                    'product_id' => $orderItem['product_id'],
                    'delta'      => -$orderItem['quantity'],
                    'reason'     => 'order',
                    'order_id'   => $order->id,
                    'user_id'    => Auth::id(),
                ]);
            }

            return $order;
        });

        // Gost pamti sopstvenu porudžbinu kroz sesiju — jedini način da kasnije
        // pristupi success/cod-success/payment-failed stranici bez naloga (vidi OrderPolicy).
        if (Auth::guest()) {
            $request->session()->push('guest_order_ids', $order->id);
        }

         // ✅ ISPRAZNI KORPU IZ BAZE
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->cart) {
                $user->cart->delete();
                Log::info('Cart deleted for user after order', [
                    'user_id' => $user->id,
                    'order_id' => $order->id
                ]);
            }
        }

        Log::info('Order created', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'total' => $order->total_price
        ]);

        if ($validated['payment_method'] === 'paypal') {
            Log::info('Redirecting to PayPal for order: ' . $order->id);
            return inertia()->location(route('paypal.createPayment', $order->id));
        } else {
            Log::info('COD order - redirecting to success for order: ' . $order->id);
            return inertia()->location(route('order.cod.success', $order->id));
        }
    }
}