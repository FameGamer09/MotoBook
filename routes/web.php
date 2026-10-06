<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/SD/oliva', [DataController::class, 'handleSerialization']);

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::post('/pos/process-sale', [PosController::class, 'processSale'])->name('pos.processSale');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/rider/orders/{order}/navigation', function (Order $order) {
        if (auth()->user()->isRider() && auth()->id() !== $order->rider_id) {
            abort(403);
        }
        if (! auth()->user()->isRider() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('rider.navigation', compact('order'));
    })->name('rider.orders.navigation');

    Route::get('/customer/orders/{order}/track', function (Order $order) {
        $user = auth()->user();
        if (! $user->isAdmin() && $user->id !== $order->customer_id && $user->id !== $order->rider_id) {
            abort(403);
        }

        return view('customer.track', compact('order'));
    })->name('customer.orders.track');

    Route::post('/orders/{order}/location', [OrderTrackingController::class, 'updateLocation'])
        ->name('orders.location.update');

    Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'showTracking'])
        ->name('orders.tracking.show');
});
