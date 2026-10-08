<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RiderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
    Route::get('/merchants/{store}', [MerchantController::class, 'show'])->name('merchants.show');
    Route::patch('/merchants/{store}/verify', [MerchantController::class, 'verify'])->name('merchants.verify');
    Route::patch('/merchants/{store}/unverify', [MerchantController::class, 'unverify'])->name('merchants.unverify');
    Route::patch('/merchants/{store}/ban', [MerchantController::class, 'ban'])->name('merchants.ban');
    Route::patch('/merchants/{store}/activate', [MerchantController::class, 'activate'])->name('merchants.activate');

    Route::get('/riders', [RiderController::class, 'index'])->name('riders.index');
    Route::get('/riders/{rider}', [RiderController::class, 'show'])->name('riders.show');
    Route::get('/riders/{rider}/license', [RiderController::class, 'license'])->name('riders.license');
    Route::patch('/riders/{rider}/verify', [RiderController::class, 'verify'])->name('riders.verify');
    Route::patch('/riders/{rider}/unverify', [RiderController::class, 'unverify'])->name('riders.unverify');
    Route::patch('/riders/{rider}/ban', [RiderController::class, 'ban'])->name('riders.ban');
    Route::patch('/riders/{rider}/activate', [RiderController::class, 'activate'])->name('riders.activate');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::patch('/customers/{customer}/ban', [CustomerController::class, 'ban'])->name('customers.ban');
    Route::patch('/customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');

    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::patch('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::patch('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
});
