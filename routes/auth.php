<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PhoneOtpController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'cache.headers:private;no_store;no_cache;must_revalidate;max_age=0'])->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    foreach (['google', 'facebook'] as $provider) {
        Route::get("register/auth/{$provider}", [SocialLoginController::class, 'registerRedirect'])
            ->defaults('provider', $provider)
            ->name("register.social.{$provider}");
    }

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    foreach (['google', 'facebook', 'apple'] as $provider) {
        Route::get("auth/{$provider}", [SocialLoginController::class, 'redirect'])
            ->defaults('provider', $provider)
            ->name("login.social.{$provider}");
    }

    Route::match(['get', 'post'], 'auth/apple/callback', [SocialLoginController::class, 'callback'])
        ->defaults('provider', 'apple')
        ->name('login.social.apple.callback');

    foreach (['google', 'facebook'] as $provider) {
        Route::get("auth/{$provider}/callback", [SocialLoginController::class, 'callback'])
            ->defaults('provider', $provider)
            ->name("login.social.{$provider}.callback");
    }

    Route::get('login/phone', [PhoneOtpController::class, 'create'])
        ->name('login.phone');

    Route::post('login/phone', [PhoneOtpController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('login.phone.send');

    Route::get('login/phone/verify', [PhoneOtpController::class, 'showVerification'])
        ->name('login.phone.verify.show');

    Route::post('login/phone/verify', [PhoneOtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('login.phone.verify');

    Route::post('login/phone/resend', [PhoneOtpController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('login.phone.resend');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware(['auth', 'cache.headers:private;no_store;no_cache;must_revalidate;max_age=0'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
