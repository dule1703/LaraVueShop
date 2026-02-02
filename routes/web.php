<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Cart;
use App\Models\Order;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\PayPalController;


Route::get('/', function () {
    $categories = \App\Models\Category::with(['products' => function ($query) {
        $query->where('is_active', true);
    }])->get();

    return Inertia::render('Shop', [
        'categories' => $categories,
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

// Shop stranica (/shop) – ista logika
Route::get('/shop', function () {
    $categories = \App\Models\Category::with(['products' => function ($query) {
        $query->where('is_active', true);
    }])->get();

    return Inertia::render('Shop', [
        'categories' => $categories,
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('shop');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function(){
            Route::resource('categories', CategoryController::class);
            Route::resource('products', ProductController::class);
            Route::resource('orders', OrderController::class)->only([
                'index', 'show'
            ]);       
        });


Route::get('/paypal/success/{order}', [PayPalController::class, 'success'])->name('paypal.success');
Route::get('/paypal/cancel/{order}', [PayPalController::class, 'cancel'])->name('paypal.cancel');

// Route for createPayment
Route::get('/paypal/create-payment/{order}', [PayPalController::class, 'createPayment'])->name('paypal.createPayment');

// Checkout route 
Route::get('/checkout', function () {   
    $order = Order::latest()->first(); 
    return Inertia::render('Checkout', ['order' => $order]);
})->name('checkout');

// Success route (OrderSuccess.vue)
Route::get('/order/success/{order}', function (Order $order) {
    return Inertia::render('Orders/OrderSuccess', ['order' => $order]);
})->name('order.success');

//Route for failed payement
Route::get('/payment/failed/{order}', function (Order $order) {
    return Inertia::render('PaymentFailed', ['order' => $order]);
})->name('payment.failed');

// Javne rute – bez auth i admin middleware-a
// Route::get('/shop', [ProductController::class, 'publicIndex'])->name('shop');
Route::get('/product/{product}', [ProductController::class, 'publicShow'])->name('product.details');

// Cart stranica - dostupna SVIMA (guest i auth)
Route::get('/cart', function() {
    return Inertia::render('Cart');
})->name('cart');

// Cart API rute - SAMO za ulogovane korisnike
Route::middleware('auth')->group(function () {
    // API endpoint za dobijanje korpe (JSON)
    Route::get('/api/cart', function () {
        $user = Auth::user();
        $cart = $user->cart ?? Cart::create([
            'user_id' => $user->id,
            'items' => json_encode([]),
        ]);

        return response()->json(['cart' => $cart]);
    })->name('cart.show');

    // API endpoint za sinhronizaciju korpe
    Route::post('/api/cart/sync', function (Request $request) {
        $user = Auth::user();
        $cart = $user->cart ?? Cart::create([
            'user_id' => $user->id,
            'items' => json_encode([]),
        ]);

        $cart->update(['items' => json_encode($request->items)]);

        return response()->json(['success' => true]);
    })->name('cart.sync');
});