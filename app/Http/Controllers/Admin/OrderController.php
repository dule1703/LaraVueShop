<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InventoryService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

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

        // Otkazivanje mora da vrati zalihu (isti mehanizam kao PayPal cancel/
        // fail — InventoryService::restoreStock, Faza 5), ne samo promeni
        // status. Za sve ostale statuse ostaje plain update — restoreStock
        // je namenjen isključivo terminalnom "porudžbina neće biti ispunjena"
        // prelazu, sam postavlja status unutar zaključane transakcije
        // (ne duplirati $order->update() posle).
        if ($validated['status'] === 'cancelled') {
            $this->inventory->restoreStock($order, 'admin_cancel', 'cancelled');
        } else {
            $order->update(['status' => $validated['status']]);
        }

        return back()->with('success', 'Status porudžbine je izmenjen.');
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
            ->with('success', 'Porudžbina je obrisana.');
    }
}