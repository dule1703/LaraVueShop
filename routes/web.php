<?php
// routes/web.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Order;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\PublisherController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\Api\CartController;

// Javni katalog knjiga (početna i /shop su ista stranica)
Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/shop', [CatalogController::class, 'index'])->name('shop');
Route::get('/knjiga/{slug}', [CatalogController::class, 'show'])->name('book.show');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function(){
            Route::resource('categories', CategoryController::class);
            Route::resource('products', ProductController::class);
            Route::resource('books', BookController::class)->except('show');
            Route::resource('authors', AuthorController::class)->except('show');
            Route::resource('publishers', PublisherController::class)->except('show');
            Route::resource('orders', OrderController::class)->only([
                'index', 'show', 'update', 'destroy'
            ]);      
        });

// Checkout route
Route::get('/checkout', function () {
    return Inertia::render('Checkout');
})->name('checkout');

// PayPal rute
Route::get('/paypal/success/{order}', [PayPalController::class, 'success'])
    ->middleware('can:view,order')
    ->name('paypal.success');
Route::get('/paypal/cancel/{order}', [PayPalController::class, 'cancel'])
    ->middleware('can:view,order')
    ->name('paypal.cancel');
Route::get('/paypal/create-payment/{order}', [PayPalController::class, 'createPayment'])
    ->middleware('can:view,order')
    ->name('paypal.createPayment');

// Korisničke success/fail rute (sve u Orders folderu)
// IDOR zaštita: vlasništvo se proverava kroz OrderPolicy (auth korisnik → user_id,
// gost → order_id sačuvan u sesiji pri kreiranju porudžbine). Neovlašćen pristup -> 403.
Route::get('/order/success/{order}', function (Order $order) {
    return Inertia::render('Orders/OrderSuccess', ['order' => $order]);
})->middleware('can:view,order')->name('order.success');

Route::get('/order/cod-success/{order}', function (Order $order) {
    return Inertia::render('Orders/OrderCodSuccess', ['order' => $order]);
})->middleware('can:view,order')->name('order.cod.success');

Route::get('/payment/failed/{order}', function (Order $order) {
    return Inertia::render('Orders/PaymentFailed', ['order' => $order]);
})->middleware('can:view,order')->name('payment.failed');

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