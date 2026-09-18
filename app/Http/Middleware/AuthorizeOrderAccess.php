<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ista provera vlasništva kao OrderPolicy@view, za rute čiji kontroler ne
 * tipizira {order} kao Order $order (pa Laravel ne radi implicitni route
 * model binding i "can:view,order" middleware nema šta da autorizuje).
 * Koristi se tamo gde ne diramo sam kontroler (npr. PayPalController::cancel).
 */
class AuthorizeOrderAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        if (! $order instanceof Order) {
            $order = Order::findOrFail($order);
        }

        Gate::authorize('view', $order);

        return $next($request);
    }
}
