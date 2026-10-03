<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::post('/email', [EmailController::class, 'sendingEmail']);

Route::post('/students', [StudentController::class, 'store']);
Route::get('/send-data', [RegisterController::class, 'sendData']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/orders/{order}/location', [OrderTrackingController::class, 'updateLocation'])
        ->name('api.orders.location.update');

    Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'showTracking'])
        ->name('api.orders.tracking.show');
});