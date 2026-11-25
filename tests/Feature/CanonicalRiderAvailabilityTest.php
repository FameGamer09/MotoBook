<?php

require_once dirname(__DIR__, 2).'/admin/includes/canonical_rider_status.php';

test('only a verified online rider with an active account and no current delivery can receive an order', function () {
    $eligible = [
        'rider_status' => 'online',
        'is_verified' => true,
        'user_status' => 'active',
        'has_active_delivery' => false,
    ];

    expect(canonicalRiderCanReceiveOrders($eligible))->toBeTrue()
        ->and(canonicalRiderCanReceiveOrders([...$eligible, 'is_verified' => false]))->toBeFalse()
        ->and(canonicalRiderCanReceiveOrders([...$eligible, 'rider_status' => 'offline']))->toBeFalse()
        ->and(canonicalRiderCanReceiveOrders([...$eligible, 'user_status' => 'inactive']))->toBeFalse()
        ->and(canonicalRiderCanReceiveOrders([...$eligible, 'has_active_delivery' => true]))->toBeFalse()
        ->and(canonicalRiderCanReceiveOrders($eligible, true))->toBeFalse()
        ->and(canonicalRiderCanReceiveOrders(null))->toBeFalse();
});
