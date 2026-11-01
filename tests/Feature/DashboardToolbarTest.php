<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

test('dashboard notifications return low-stock alerts and recent inventory changes', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'status' => 'active']);
    $product = Product::factory()->create([
        'name' => 'Low stock coffee',
        'quantity' => 2,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ]);
    InventoryMovement::create([
        'product_id' => $product->id,
        'user_id' => $cashier->id,
        'type' => 'stock_in',
        'quantity' => 3,
        'previous_quantity' => 0,
        'new_quantity' => 3,
        'reference' => 'TEST-1',
    ]);

    $this->actingAs($cashier)
        ->getJson(route('dashboard.notifications'))
        ->assertOk()
        ->assertJsonPath('low_stock_count', 1)
        ->assertJsonFragment([
            'id' => 'low-stock-'.$product->id,
            'title' => 'Low stock coffee is low on stock',
            'url' => route('products.edit', $product),
            'kind' => 'warning',
        ])
        ->assertJsonFragment([
            'id' => 'stock-movement-1',
            'title' => 'Stock updated: Low stock coffee',
            'url' => route('products.show', $product),
            'kind' => 'activity',
        ]);
});

test('dashboard toolbar provides working inventory and sales searches', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'status' => 'active']);

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-open-global-search', false)
        ->assertSee('id="global-search-dialog"', false)
        ->assertSee(route('products.index'), false)
        ->assertSee(route('transactions.index'), false)
        ->assertSee('name="search"', false);
});

test('merchant notification link opens the pending customer orders filter', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    Store::factory()->create(['user_id' => $merchant->id]);

    $this->actingAs($merchant)
        ->get(route('merchant.dashboard'))
        ->assertOk()
        ->assertSee(route('merchant.orders.index', ['status' => 'pending']), false)
        ->assertSee('View pending orders', false);
});

test('dashboard notification data is limited to admin and cashier roles', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($customer)
        ->getJson(route('dashboard.notifications'))
        ->assertForbidden();
});
