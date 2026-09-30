<?php
// routes/web.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Order;
use App\Models\Product;
use App\Http\Controllers\AddressController;
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

Route::get('/dashboard', function (Request $request) {
    $stats = null;

    if ($request->user()->role === 'admin') {
        // Read-only brojevi za admin početnu — isti low-stock prag (5) kao
        // BookController::DEFAULT_LOW_STOCK_THRESHOLD (Faza 5).
        $stats = [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'total_products' => Product::count(),
            'low_stock_products' => Product::whereNotNull('stock')->where('stock', '<', 5)->count(),
        ];
    }

    return Inertia::render('Dashboard', ['stats' => $stats]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Sačuvane adrese — {address} je običan int (bez implicitnog model binding-a),
    // lookup ide kroz $user->addresses() u AddressController-u (IDOR zaštita).
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('addresses.store');
    // whereNumber: nenumerički {address} -> 404, ne TypeError (500) na int parametru.
    Route::patch('/profile/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
    Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
    Route::patch('/profile/addresses/{address}/default', [AddressController::class, 'setDefault'])->whereNumber('address')->name('addresses.setDefault');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function(){
            Route::resource('categories', CategoryController::class);
            Route::resource('products', ProductController::class);
            Route::resource('books', BookController::class)->except('show');
            Route::post('books/{book}/restock', [BookController::class, 'restock'])->name('books.restock');
            Route::resource('authors', AuthorController::class)->except('show');
            Route::resource('publishers', PublisherController::class)->except('show');
            Route::resource('orders', OrderController::class)->only([
                'index', 'show', 'update', 'destroy'
            ]);      
        });

// Checkout route
// Ulogovan korisnik dobija svoje sačuvane adrese (podrazumevana prva); gost prazan niz.
Route::get('/checkout', function (Request $request) {
    return Inertia::render('Checkout', [
        'addresses' => $request->user()
            ? $request->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get()
            : [],
    ]);
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

// Sveži podaci o proizvodima iz korpe (naziv/cena/slika) - dostupno i gostu,
// jer Cart stranica ne zahteva auth. Cena/naziv se NIKAD ne čuvaju u samoj korpi.
Route::get('/api/cart/products', [CartController::class, 'productDetails'])->name('api.cart.products');

// ============================================================================
// CART API RUTE - SAMO ZA ULOGOVANE KORISNIKE (perzistencija {product_id, quantity})
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
