<?php
// routes/web.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Order;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\Api\CartController;

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
                'index', 'show', 'update', 'destroy'
            ]);      
        });

// Checkout route
Route::get('/checkout', function () {  
    $order = Order::latest()->first();
    return Inertia::render('Checkout', ['order' => $order]);
})->name('checkout');        

// PayPal rute
Route::get('/paypal/success/{order}', [PayPalController::class, 'success'])->name('paypal.success');
Route::get('/paypal/cancel/{order}', [PayPalController::class, 'cancel'])->name('paypal.cancel');
Route::get('/paypal/create-payment/{order}', [PayPalController::class, 'createPayment'])->name('paypal.createPayment');

// Korisničke success/fail rute (sve u Orders folderu)
Route::get('/order/success/{order}', function (Order $order) {
    return Inertia::render('Orders/OrderSuccess', ['order' => $order]);
})->name('order.success');

Route::get('/order/cod-success/{order}', function (Order $order) {
    return Inertia::render('Orders/OrderCodSuccess', ['order' => $order]);
})->name('order.cod.success');

Route::get('/payment/failed/{order}', function (Order $order) {
    return Inertia::render('Orders/PaymentFailed', ['order' => $order]);
})->name('payment.failed');

// Javne rute – bez auth i admin middleware-a
Route::get('/product/{product}', [ProductController::class, 'publicShow'])->name('product.details');

// Cart stranica - dostupna SVIMA (guest i auth)
Route::get('/cart', function() {
    return Inertia::render('Cart');
})->name('cart');

// ============================================================================
// CART API RUTE - SAMO ZA ULOGOVANE KORISNIKE
// ============================================================================
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('api.cart.show');
    Route::post('/cart/sync', [CartController::class, 'sync'])->name('api.cart.sync');
});

// ============================================================================
// LOGOUT RUTA - Inertia-friendly (NE briše korpu u bazi!)
// ============================================================================
Route::post('/logout', function (Request $request) {
    // VAŽNO: Ne brišemo korpu u bazi!
    // Korpa ostaje sačuvana za svaki nalog
    
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

// Kreiranje porudžbine (obrada forme + preusmeravanje na PayPal)
Route::post('/orders', [App\Http\Controllers\OrderController::class, 'store'])
    ->name('orders.store');