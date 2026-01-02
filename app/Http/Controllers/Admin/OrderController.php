<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Prikaz liste svih porudžbina
     */
    public function index()
    {
        $orders = Order::with('user')->latest()->get();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders
        ]);
    }

    /**
     * Prikaz detalja jedne porudžbine
     */
    public function show(Order $order)
    {
        $order->load('items.product', 'user');

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order
        ]);
    }

    
}