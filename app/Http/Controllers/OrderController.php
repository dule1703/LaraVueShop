<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        ]);

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
            'customer_email'  => Auth::check() ? Auth::user()->email : $validated['email'],
        ]);

        foreach ($validated['items'] as $item) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $item['id'],
                'product_name' => $item['name'],
                'product_price' => $item['price'],
                'quantity'     => $item['quantity'],
            ]);
        }

        return inertia()->location(route('paypal.createPayment', $order->id));
    }
}