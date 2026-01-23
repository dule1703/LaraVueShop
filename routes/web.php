<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\PayPalController;
use App\Models\Order;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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