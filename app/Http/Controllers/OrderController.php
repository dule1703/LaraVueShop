<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function store(Request $request)
    {
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
            'items.*.id'   => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.name'     => 'required|string|max:255',
            'total_price'  => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:paypal,cod',
        ]);

        // Kreiraj porudžbinu
        $order = Order::create([
            'user_id'         => Auth::id(),
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'address'         => $validated['address'],
            'city'            => $validated['city'],
            'postal_code'     => $validated['postal_code'],
            'phone'           => $validated['phone'],
            'notes'           => $validated['notes'] ?? null,
            'total_price'     => $validated['total_price'],
            'status'          => 'pending',
            'payment_method'  => $validated['payment_method'],
            'customer_email'  => Auth::check() ? Auth::user()->email : $validated['email'],
        ]);

        // Kreiraj order items
        foreach ($validated['items'] as $item) {
            OrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => $item['id'],
                'product_name'  => $item['name'],
                'product_price' => $item['price'],
                'quantity'      => $item['quantity'],
            ]);
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