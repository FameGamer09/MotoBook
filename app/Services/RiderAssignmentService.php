<?php

namespace App\Services;

use App\Exceptions\DeliveryException;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;

class RiderAssignmentService
{
    /** How far (km) we're willing to search for a rider. Tune as needed. */
    private const SEARCH_RADIUS_KM = 5;

    /**
     * "Assign Nearest Available Rider" (System)
     * Creates the Delivery record and pushes it to the closest online rider.
     */
    public function assignNearestRider(Order $order, array $excludedRiderIds = []): Delivery
    {
        return DB::transaction(function () use ($order, $excludedRiderIds) {
            $lockedOrder = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $delivery = Delivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['order_id' => $lockedOrder->id],
                    ['status' => 'pending_assignment']
                );

            if (! in_array($lockedOrder->status, ['ready_for_pickup', 'assigned'], true)) {
                return $delivery;
            }

            if ($delivery->status !== 'pending_assignment' && $delivery->rider_id !== null) {
                return $delivery;
            }

            $store = $lockedOrder->store()->withoutGlobalScopes()->first();

            if (
                ! $store
                || ! $this->hasValidCoordinates($store->latitude, $store->longitude)
                || ! $this->hasValidCoordinates($lockedOrder->customer_lat, $lockedOrder->customer_lng)
            ) {
                return $delivery;
            }

            $rider = $this->findNearestAvailableRider($store->latitude, $store->longitude, $excludedRiderIds);

            if (! $rider) {
                // no riders available right now — leave as pending_assignment.
                // a scheduled job should retry this periodically.
                return $delivery;
            }

            $rider = Rider::withoutGlobalScopes()->lockForUpdate()
                ->where('is_verified', true)
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->find($rider->id);

            if (! $rider || $rider->status !== 'online') {
                return $delivery;
            }

            $distance = $this->haversineDistance(
                $store->latitude, $store->longitude,
                $rider->current_latitude, $rider->current_longitude
            );

            $delivery->update([
                'rider_id' => $rider->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'distance_km' => $distance,
                'eta_minutes' => $this->estimateEtaMinutes($distance),
                'base_fare' => $this->calculateBaseFare($distance),
            ]);

            $lockedOrder->update(['status' => 'assigned', 'rider_id' => $rider->id]);

            $rider->update(['status' => 'busy']);

            return Delivery::withoutGlobalScopes()->findOrFail($delivery->id);
        });
    }

    public function assignPendingDeliveries(): int
    {
        $pendingDeliveries = Delivery::withoutGlobalScopes()
            ->with('order.store')
            ->where('status', 'pending_assignment')
            ->whereHas('order', function ($query) {
                $query->where('status', 'ready_for_pickup')
                    ->whereNotNull('customer_lat')
                    ->whereNotNull('customer_lng')
                    ->whereHas('store', function ($storeQuery) {
                        $storeQuery->whereNotNull('latitude')
                            ->whereNotNull('longitude');
                    });
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        $assignedCount = 0;

        foreach ($pendingDeliveries as $delivery) {
            $updatedDelivery = $this->assignNearestRider($delivery->order, []);

            if ($updatedDelivery->rider_id !== null) {
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * "Accept Order?" -> Yes (Rider)
     */
    public function riderAccept(Delivery $delivery): Delivery
    {
        return $this->transitionDelivery($delivery, 'assigned', [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    /**
     * "Accept Order?" -> No -> "Reject Order" -> "Return to Available Riders"
     */
    public function riderReject(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $lockedDelivery = Delivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->id);
            $this->assertStatus($lockedDelivery, 'assigned');

            $rejectedRiderId = $lockedDelivery->rider_id;
            $order = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($lockedDelivery->order_id);

            $lockedDelivery->update([
                'status' => 'pending_assignment',
                'rider_id' => null,
                'rejected_at' => now(),
            ]);
            $order->update(['status' => 'ready_for_pickup', 'rider_id' => null]);

            if ($rejectedRiderId) {
                Rider::withoutGlobalScopes()
                    ->whereKey($rejectedRiderId)
                    ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                    ->where('is_verified', true)
                    ->update(['status' => 'online']);
            }

            return $this->assignNearestRider($order, $rejectedRiderId ? [$rejectedRiderId] : []);
        });
    }

    public function startPickup(Delivery $delivery): Delivery
    {
        return $this->transitionDelivery($delivery, 'accepted', ['status' => 'en_route_to_pickup']);
    }

    public function arrivePickup(Delivery $delivery): Delivery
    {
        return $this->transitionDelivery($delivery, 'en_route_to_pickup', ['status' => 'arrived_at_pickup']);
    }

    public function confirmPickup(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $lockedDelivery = Delivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->id);
            $this->assertStatus($lockedDelivery, 'arrived_at_pickup');
            $lockedDelivery->update([
                'status' => 'picked_up',
                'picked_up_at' => now(),
            ]);

            Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($lockedDelivery->order_id)
                ->update(['status' => 'out_for_delivery']);

            return Delivery::withoutGlobalScopes()->findOrFail($lockedDelivery->id);
        });
    }

    public function startCustomerDelivery(Delivery $delivery): Delivery
    {
        return $this->transitionDelivery($delivery, 'picked_up', ['status' => 'en_route_to_customer']);
    }

    public function arriveCustomer(Delivery $delivery): Delivery
    {
        return $this->transitionDelivery($delivery, 'en_route_to_customer', ['status' => 'arrived_at_customer']);
    }

    /**
     * "Complete Delivery" -> credits rider wallet, closes out the order.
     */
    public function completeDelivery(Delivery $delivery, ?string $proofPhoto = null, float $tip = 0): Delivery
    {
        return DB::transaction(function () use ($delivery, $proofPhoto, $tip) {
            $delivery = Delivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->id);
            $this->assertStatus($delivery, 'arrived_at_customer');

            if ($tip < 0) {
                throw new DeliveryException('A delivery tip cannot be negative.');
            }

            $totalEarning = $delivery->base_fare + $delivery->incentive + $tip;

            $delivery->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'proof_photo' => $proofPhoto,
                'tip' => $tip,
                'total_earning' => $totalEarning,
            ]);

            Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id)
                ->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

            $rider = Rider::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->rider_id);
            $rider->update([
                'status' => $rider->user()->where('status', 'active')->exists() && $rider->is_verified ? 'online' : 'offline',
                'total_deliveries' => $rider->total_deliveries + 1,
            ]);

            // credit the rider's wallet — matches "Earnings" screen (Base Fare + Incentive + Tip)
            $wallet = $rider->user->wallet ?? $rider->user->wallet()->create(['balance' => 0]);
            $wallet->credit(
                $totalEarning,
                "Delivery earning for order #{$delivery->order->order_number}",
                $delivery
            );

            return Delivery::withoutGlobalScopes()->findOrFail($delivery->id);
        });
    }

    // ---- helpers ----

    private function findNearestAvailableRider(float $lat, float $lng, array $excludedRiderIds = []): ?Rider
    {
        // simple bounding-box + Haversine ordering. For production scale,
        // swap this for a spatial index (MySQL ST_Distance_Sphere / PostGIS).
        return Rider::withoutGlobalScopes()
            ->where('status', 'online')
            ->where('is_verified', true)
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->when($excludedRiderIds, fn ($query) => $query->whereNotIn('id', $excludedRiderIds))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get()
            ->map(function ($rider) use ($lat, $lng) {
                $rider->distance = $this->haversineDistance($lat, $lng, $rider->current_latitude, $rider->current_longitude);

                return $rider;
            })
            ->filter(fn ($rider) => $rider->distance <= self::SEARCH_RADIUS_KM)
            ->sortBy('distance')
            ->first();
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    private function hasValidCoordinates(float|string|null $latitude, float|string|null $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }

    private function estimateEtaMinutes(float $distanceKm): int
    {
        $avgSpeedKmh = 25; // rough motorcycle city-traffic average

        return (int) ceil(($distanceKm / $avgSpeedKmh) * 60);
    }

    private function calculateBaseFare(float $distanceKm): float
    {
        $flagDown = 30; // ₱30 base
        $perKm = 8;     // ₱8/km

        return round($flagDown + ($distanceKm * $perKm), 2);
    }

    private function assertStatus(Delivery $delivery, string $expected): void
    {
        if ($delivery->status !== $expected) {
            throw new DeliveryException(
                "Delivery #{$delivery->id} must be '{$expected}' for this action, currently '{$delivery->status}'."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transitionDelivery(Delivery $delivery, string $expected, array $attributes): Delivery
    {
        return DB::transaction(function () use ($delivery, $expected, $attributes) {
            $lockedDelivery = Delivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($delivery->id);
            $this->assertStatus($lockedDelivery, $expected);
            $lockedDelivery->update($attributes);

            return Delivery::withoutGlobalScopes()->findOrFail($lockedDelivery->id);
        });
    }
}
