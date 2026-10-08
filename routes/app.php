<?php

use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\CustomDeliveryController;
use App\Http\Controllers\Customer\ExperienceController;
use App\Http\Controllers\Customer\HomeController as CustomerHomeController;
use App\Http\Controllers\Customer\ItemDetailController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Customer\StoreDetailController;
use App\Http\Controllers\Rider\CustomDeliveryController as RiderCustomDeliveryController;
use App\Http\Controllers\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Merchant\MenuController as MerchantMenuController;
use App\Http\Controllers\Merchant\OnboardingController as MerchantOnboardingController;
use App\Http\Controllers\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Merchant\SalesController as MerchantSalesController;
use App\Http\Controllers\Merchant\SettingsController as MerchantSettingsController;
use App\Http\Controllers\Merchant\StoreController as MerchantStoreController;
use App\Http\Controllers\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Rider\OnboardingController as RiderOnboardingController;
use App\Http\Controllers\Rider\ProfileController as RiderProfileController;
use App\Models\MenuItem;
use App\Models\Store;
use App\Services\ManagementCatalogSync;
use Illuminate\Support\Facades\Route;

Route::bind('store', function (string $value) {
    app(ManagementCatalogSync::class)->sync();

    return Store::withoutGlobalScopes()
        ->where('slug', $value)
        ->orWhere('id', ctype_digit($value) ? (int) $value : 0)
        ->firstOrFail();
});

Route::bind('menuItem', function (string $value) {
    app(ManagementCatalogSync::class)->sync();

    return MenuItem::withoutGlobalScopes()->findOrFail($value);
});

// ---- Customer ----
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/restaurants', [CustomerHomeController::class, 'index'])->name('restaurants');
    Route::get('/restaurants/{store:slug}', [StoreDetailController::class, 'show'])->name('restaurants.show');
    Route::post('/favorites/{store}', [ExperienceController::class, 'toggleFavorite'])->name('favorites.toggle');

    Route::get('/custom-delivery', [CustomDeliveryController::class, 'create'])->name('custom-delivery.create');
    Route::post('/custom-delivery', [CustomDeliveryController::class, 'store'])->name('custom-delivery.store');
    Route::get('/custom-deliveries', [CustomDeliveryController::class, 'index'])->name('custom-deliveries.index');
    Route::get('/custom-deliveries/{customDelivery}/confirmation', [CustomDeliveryController::class, 'confirmation'])->name('custom-deliveries.confirmation');
    Route::get('/custom-deliveries/{customDelivery}/tracking', [CustomDeliveryController::class, 'tracking'])->name('custom-deliveries.tracking');
    Route::post('/custom-deliveries/{customDelivery}/cancel', [CustomDeliveryController::class, 'cancel'])->name('custom-deliveries.cancel');
    Route::get('/custom-deliveries/{customDelivery}', [CustomDeliveryController::class, 'show'])->name('custom-deliveries.show');

    Route::get('/payments', [ExperienceController::class, 'payments'])->name('payments');
    Route::patch('/payments', [ExperienceController::class, 'updatePayments'])->name('payments.update');
    Route::get('/settings', [ExperienceController::class, 'settings'])->name('settings');
    Route::patch('/settings', [ExperienceController::class, 'updateSettings'])->name('settings.update');
    Route::get('/notifications', [ExperienceController::class, 'notifications'])->name('notifications');
    Route::delete('/notifications', [ExperienceController::class, 'clearNotifications'])->name('notifications.clear');
    Route::view('/help', 'customer.help')->name('help');

    Route::get('/items/{menuItem}', [ItemDetailController::class, 'show'])->name('items.show');
    Route::post('/items/{menuItem}/add-to-cart', [ItemDetailController::class, 'addToCart'])->name('items.addToCart');

    Route::get('/profile', [CustomerProfileController::class, 'show'])->name('profile');
    Route::get('/profile/edit', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');

    Route::get('/addresses', [CustomerProfileController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [CustomerProfileController::class, 'storeAddress'])->name('addresses.store');
    Route::patch('/addresses/{address}/location', [CustomerProfileController::class, 'updateAddressLocation'])->name('addresses.location');
    Route::patch('/addresses/{address}/default', [CustomerProfileController::class, 'setDefaultAddress'])->name('addresses.default');
    Route::delete('/addresses/{address}', [CustomerProfileController::class, 'destroyAddress'])->name('addresses.destroy');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/{menuItem}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{lineKey}', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::delete('/cart/{lineKey}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'placeOrder'])->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/review', [CustomerOrderController::class, 'review'])->name('orders.review');
    Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking'])->name('orders.tracking');
});

// ---- Merchant ----
Route::middleware(['auth', 'role:merchant'])->prefix('merchant')->name('merchant.')->group(function () {
    Route::get('/onboarding', [MerchantOnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [MerchantOnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/dashboard', [MerchantDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [MerchantOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/accept', [MerchantOrderController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{order}/reject', [MerchantOrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{order}/preparing', [MerchantOrderController::class, 'preparing'])->name('orders.preparing');
    Route::post('/orders/{order}/ready', [MerchantOrderController::class, 'ready'])->name('orders.ready');
    Route::get('/orders/{order}', [MerchantOrderController::class, 'show'])->name('orders.show');
    Route::get('/menu', [MerchantMenuController::class, 'index'])->name('menu.index');
    Route::post('/menu/categories', [MerchantMenuController::class, 'storeCategory'])->name('menu.categories.store');
    Route::delete('/menu/categories/{category}', [MerchantMenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::post('/menu/items', [MerchantMenuController::class, 'storeItem'])->name('menu.items.store');
    Route::get('/menu/items/{menuItem}/edit', [MerchantMenuController::class, 'edit'])->name('menu.items.edit');
    Route::get('/menu/items/{menuItem}/options', [MerchantMenuController::class, 'options'])->name('menu.items.options');
    Route::post('/menu/items/{menuItem}/options/groups', [MerchantMenuController::class, 'storeOptionGroup'])->name('menu.items.options.groups.store');
    Route::post('/menu/options/groups/{group}/options', [MerchantMenuController::class, 'storeOption'])->name('menu.options.store');
    Route::delete('/menu/options/groups/{group}', [MerchantMenuController::class, 'destroyOptionGroup'])->name('menu.options.groups.destroy');
    Route::delete('/menu/options/{option}', [MerchantMenuController::class, 'destroyOption'])->name('menu.options.destroy');
    Route::patch('/menu/items/{menuItem}', [MerchantMenuController::class, 'updateItem'])->name('menu.items.update');
    Route::delete('/menu/items/{menuItem}', [MerchantMenuController::class, 'destroyItem'])->name('menu.items.destroy');
    Route::get('/store', [MerchantStoreController::class, 'edit'])->name('store.edit');
    Route::patch('/store', [MerchantStoreController::class, 'update'])->name('store.update');
    Route::post('/store/toggle', [MerchantStoreController::class, 'toggle'])->name('store.toggle');
    Route::get('/sales', [MerchantSalesController::class, 'index'])->name('sales.index');
    Route::get('/settings', [MerchantSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [MerchantSettingsController::class, 'update'])->name('settings.update');
});

// ---- Rider ----
Route::middleware(['auth', 'role:rider'])->prefix('rider')->name('rider.')->group(function () {
    Route::get('/onboarding', [RiderOnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [RiderOnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/dashboard', [RiderDashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [RiderProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile', [RiderProfileController::class, 'update'])->name('profile.update');
    Route::post('/deliveries/{delivery}/accept', [RiderDashboardController::class, 'accept'])->name('deliveries.accept');
    Route::post('/deliveries/{delivery}/reject', [RiderDashboardController::class, 'reject'])->name('deliveries.reject');
    Route::post('/deliveries/{delivery}/start-pickup', [RiderDashboardController::class, 'startPickup'])->name('deliveries.startPickup');
    Route::post('/deliveries/{delivery}/arrive-pickup', [RiderDashboardController::class, 'arrivePickup'])->name('deliveries.arrivePickup');
    Route::post('/deliveries/{delivery}/confirm-pickup', [RiderDashboardController::class, 'confirmPickup'])->name('deliveries.confirmPickup');
    Route::post('/deliveries/{delivery}/start-customer', [RiderDashboardController::class, 'startCustomer'])->name('deliveries.startCustomer');
    Route::post('/deliveries/{delivery}/arrive-customer', [RiderDashboardController::class, 'arriveCustomer'])->name('deliveries.arriveCustomer');
    Route::post('/deliveries/{delivery}/complete', [RiderDashboardController::class, 'complete'])->name('deliveries.complete');
    Route::post('/location', [RiderDashboardController::class, 'updateLocation'])->middleware('throttle:120,1')->name('location.update');
    Route::post('/status/toggle', [RiderDashboardController::class, 'toggleStatus'])->name('status.toggle');
    Route::post('/custom-deliveries/{customDelivery}/accept', [RiderCustomDeliveryController::class, 'accept'])->name('custom-deliveries.accept');
    Route::post('/custom-deliveries/{customDelivery}/reject', [RiderCustomDeliveryController::class, 'reject'])->name('custom-deliveries.reject');
    Route::post('/custom-deliveries/{customDelivery}/pickup', [RiderCustomDeliveryController::class, 'pickup'])->name('custom-deliveries.pickup');
    Route::post('/custom-deliveries/{customDelivery}/complete', [RiderCustomDeliveryController::class, 'complete'])->name('custom-deliveries.complete');
    Route::post('/custom-deliveries/{customDelivery}/location', [RiderCustomDeliveryController::class, 'location'])->middleware('throttle:120,1')->name('custom-deliveries.location');
});
