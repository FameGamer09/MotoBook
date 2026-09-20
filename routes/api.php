<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\RegisterController;

Route::post('/email', [EmailController::class, 'sendingEmail']);

Route::post('/students', [StudentController::class, 'store']);
Route::get('/send-data', [RegisterController::class, 'sendData']);