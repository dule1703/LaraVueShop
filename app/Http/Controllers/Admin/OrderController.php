<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of all orders
     */
    public function index()
    {
        $orders = Order::with('user')
            ->latest()
            ->paginate(10);  

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders
        ]);
    }

    /**
     * Display the details of a single order
     */
    public function show(Order $order)
    {
        $order->load([
            'user',                    
            'items.product',           
            'payment',                 
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,paid,completed,cancelled,failed,shipped,delivered',
        ]);

        $order->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Order status updated to ' . ucfirst($validated['status']));
    }

    /**
     * Delete an order
     */
    public function destroy(Order $order)
    {
        // Opcionalno: briši i povezane stavke i payment
        $order->items()->delete();
        $order->payment()->delete();

        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('success', 'Order deleted successfully');
    }
}