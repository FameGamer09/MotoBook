<?php

declare(strict_types=1);

function autoAssignPendingRiderOrders(PDO $pdo): int
{
    $assignedCount = 0;
    $ownsTransaction = ! $pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pendingOrders = $pdo->query("SELECT rider_order.id, rider_order.shared_order_id,
                rider_order.merchant_lat, rider_order.merchant_lng
            FROM rider_orders AS rider_order
            LEFT JOIN orders AS legacy_order ON legacy_order.id = rider_order.shared_order_id
            WHERE rider_order.rider_id IS NULL
                AND rider_order.state = 'OFFER_RECEIVED'
                AND (rider_order.shared_order_id IS NULL OR (
                    legacy_order.id IS NOT NULL
                    AND legacy_order.rider_id IS NULL
                    AND legacy_order.order_status NOT IN ('cancelled', 'delivered')
                ))
            ORDER BY rider_order.offer_received_at, rider_order.id
            FOR UPDATE")->fetchAll();

        foreach ($pendingOrders as $order) {
            $riders = $pdo->query("SELECT rider.id,
                    (SELECT location.lat FROM rider_location_logs AS location
                     WHERE location.rider_id = rider.id
                     ORDER BY location.recorded_at DESC, location.id DESC LIMIT 1) AS current_lat,
                    (SELECT location.lng FROM rider_location_logs AS location
                     WHERE location.rider_id = rider.id
                     ORDER BY location.recorded_at DESC, location.id DESC LIMIT 1) AS current_lng
                FROM riders AS rider
                WHERE UPPER(rider.status) IN ('ON_SHIFT', 'ON_DUTY')
                    AND NOT EXISTS (
                        SELECT 1 FROM rider_orders AS active_order
                        WHERE active_order.rider_id = rider.id
                            AND active_order.state IN ('ACCEPTED', 'NAVIGATING_TO_PICKUP', 'ARRIVED_AT_PICKUP', 'ORDER_VERIFIED', 'NAVIGATING_TO_DROP_OFF', 'ARRIVED_AT_DROP_OFF', 'PROOF_SUBMITTED')
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM orders AS active_legacy_order
                        WHERE active_legacy_order.rider_id = rider.id
                            AND active_legacy_order.order_status IN ('driver_assigned', 'out_for_delivery', 'picked_up')
                    )
                ORDER BY rider.id
                FOR UPDATE")->fetchAll();

            if (! $riders) {
                break;
            }

            $pickupLat = $order['merchant_lat'] !== null ? (float) $order['merchant_lat'] : null;
            $pickupLng = $order['merchant_lng'] !== null ? (float) $order['merchant_lng'] : null;
            $distance = static function (array $rider) use ($pickupLat, $pickupLng): float {
                if ($pickupLat === null || $pickupLng === null || $rider['current_lat'] === null || $rider['current_lng'] === null) {
                    return PHP_FLOAT_MAX;
                }

                $latA = deg2rad($pickupLat);
                $latB = deg2rad((float) $rider['current_lat']);
                $deltaLat = deg2rad((float) $rider['current_lat'] - $pickupLat);
                $deltaLng = deg2rad((float) $rider['current_lng'] - $pickupLng);
                $a = sin($deltaLat / 2) ** 2 + cos($latA) * cos($latB) * sin($deltaLng / 2) ** 2;

                return 6_371_000 * 2 * asin(sqrt(min(1, $a)));
            };
            usort($riders, static fn (array $left, array $right): int => $distance($left) <=> $distance($right));

            $riderId = (int) $riders[0]['id'];
            $legacyOrderId = (int) ($order['shared_order_id'] ?? 0);
            if ($legacyOrderId > 0) {
                $legacyUpdate = $pdo->prepare("UPDATE orders
                    SET rider_id = ?, order_status = 'driver_assigned',
                        assigned_at = COALESCE(assigned_at, NOW()), updated_at = NOW()
                    WHERE id = ? AND rider_id IS NULL AND order_status NOT IN ('cancelled', 'delivered')");
                $legacyUpdate->execute([$riderId, $legacyOrderId]);
                if ($legacyUpdate->rowCount() !== 1) {
                    continue;
                }
            }

            $assign = $pdo->prepare("UPDATE rider_orders
                SET rider_id = ?, state = 'ACCEPTED', accepted_at = NOW(), offer_expires_at = NULL, updated_at = NOW()
                WHERE id = ? AND rider_id IS NULL AND state = 'OFFER_RECEIVED'");
            $assign->execute([$riderId, (int) $order['id']]);
            if ($assign->rowCount() !== 1) {
                throw new RuntimeException('Rider order changed while being assigned.');
            }
            $assignedCount++;
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Automatic rider assignment failed: '.$exception->getMessage());
    }

    return $assignedCount;
}
