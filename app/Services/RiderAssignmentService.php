<?php

namespace App\Services;

use App\Exceptions\DeliveryException;
use App\Models\CustomDelivery;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderDeliveryOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiderAssignmentService
{
    private const SEARCH_RADIUS_KM = 5;

    private const OFFER_WINDOW_SECONDS = 20;

    private const ACTIVE_DELIVERY_STATUSES = [
        'assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up',
        'en_route_to_customer', 'arrived_at_customer',
    ];

    private const ACTIVE_CUSTOM_DELIVERY_STATUSES = ['assigned', 'accepted', 'picked_up'];

    public function assignNearestRider(Order $order): Delivery
    {
        return DB::transaction(function () use ($order) {
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

            if ($delivery->status !== 'pending_assignment') {
                return $delivery;
            }

            $this->syncOffersForDelivery($delivery);

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

        $offerCount = 0;

        foreach ($pendingDeliveries as $delivery) {
            $offerCount += DB::transaction(function () use ($delivery): int {
                $lockedDelivery = Delivery::withoutGlobalScopes()->lockForUpdate()->find($delivery->id);

                if (! $lockedDelivery || $lockedDelivery->status !== 'pending_assignment') {
                    return 0;
                }

                return $this->syncOffersForDelivery($lockedDelivery);
            });
        }

        $this->advanceOfferQueues();

        return $offerCount;
    }

    public function riderOffers(Rider $rider): Collection
    {
        if ($rider->status !== 'online' || ! $rider->is_verified || $rider->user?->status !== 'active') {
            return collect();
        }

        $this->assignPendingDeliveries();

        return RiderDeliveryOffer::query()
            ->with(['delivery.order.store', 'delivery.order.items', 'delivery.offers'])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->whereHas('delivery', fn (Builder $query) => $query->where('status', 'pending_assignment'))
            ->orderBy('created_at')
            ->orderBy('queue_position')
            ->get();
    }

    public function advanceOfferQueues(): void
    {
        $deliveryIds = Delivery::withoutGlobalScopes()
            ->where('status', 'pending_assignment')
            ->orderBy('id')
            ->pluck('id');

        foreach ($deliveryIds as $deliveryId) {
            DB::transaction(function () use ($deliveryId): void {
                $delivery = Delivery::withoutGlobalScopes()->lockForUpdate()->find($deliveryId);

                if ($delivery && $delivery->status === 'pending_assignment') {
                    $this->syncOffersForDelivery($delivery);
                    $this->advanceOfferQueue($delivery);
                }
            });
        }
    }

    public function riderAccept(Delivery $delivery, Rider $rider): Delivery
    {
        return DB::transaction(function () use ($delivery, $rider): Delivery {
            $lockedDelivery = Delivery::withoutGlobalScopes()->lockForUpdate()->findOrFail($delivery->id);
            $this->assertStatus($lockedDelivery, 'pending_assignment');

            $lockedOffer = RiderDeliveryOffer::query()
                ->where('delivery_id', $lockedDelivery->id)
                ->where('rider_id', $rider->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOffer || $lockedOffer->status !== 'offered') {
                throw new DeliveryException('It is not your turn for this delivery yet.');
            }

            if ($lockedOffer->expires_at === null || $lockedOffer->expires_at->isPast()) {
                throw new DeliveryException('Your offer window expired. The next rider is being notified.');
            }

            $lockedRider = Rider::withoutGlobalScopes()->lockForUpdate()->findOrFail($rider->id);
            $this->assertRiderCanAccept($lockedRider, $lockedDelivery);

            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($lockedDelivery->order_id);
            if ($order->status !== 'ready_for_pickup') {
                throw new DeliveryException('This order is no longer waiting for rider pickup.');
            }

            $store = $order->store()->withoutGlobalScopes()->first();

            if (! $store || ! $this->hasValidCoordinates($store->latitude, $store->longitude)) {
                throw new DeliveryException('The store location is unavailable for this delivery.');
            }

            $distance = $this->haversineDistance(
                (float) $store->latitude,
                (float) $store->longitude,
                (float) $lockedRider->current_latitude,
                (float) $lockedRider->current_longitude,
            );

            if ($distance > self::SEARCH_RADIUS_KM) {
                throw new DeliveryException('You are outside the delivery area for this order.');
            }

            $lockedDelivery->update([
                'rider_id' => $lockedRider->id,
                'status' => 'accepted',
                'assigned_at' => now(),
                'accepted_at' => now(),
                'distance_km' => $distance,
                'eta_minutes' => $this->estimateEtaMinutes($distance),
                'base_fare' => $this->calculateBaseFare($distance),
            ]);
            $order->update(['status' => 'assigned', 'rider_id' => $lockedRider->id]);
            $lockedRider->update(['status' => 'busy', 'dispatch_turn_at' => now()]);
            $lockedOffer->update(['status' => 'accepted', 'responded_at' => now()]);
            RiderDeliveryOffer::query()
                ->where('delivery_id', $lockedDelivery->id)
                ->where('id', '!=', $lockedOffer->id)
                ->whereIn('status', ['waiting', 'offered'])
                ->update(['status' => 'withdrawn', 'responded_at' => now()]);

            return Delivery::withoutGlobalScopes()->findOrFail($lockedDelivery->id);
        });
    }

    public function riderReject(Delivery $delivery, Rider $rider): Delivery
    {
        return DB::transaction(function () use ($delivery, $rider): Delivery {
            $lockedDelivery = Delivery::withoutGlobalScopes()->lockForUpdate()->findOrFail($delivery->id);
            $this->assertStatus($lockedDelivery, 'pending_assignment');

            $offer = RiderDeliveryOffer::query()
                ->where('delivery_id', $lockedDelivery->id)
                ->where('rider_id', $rider->id)
                ->lockForUpdate()
                ->first();

            if (! $offer || $offer->status !== 'offered') {
                throw new DeliveryException('Only the rider whose turn is active can decline this delivery.');
            }

            if ($offer->expires_at?->isPast()) {
                $offer->update(['status' => 'expired', 'responded_at' => now()]);
                Rider::withoutGlobalScopes()->whereKey($rider->id)->update(['dispatch_turn_at' => now()]);
                $this->advanceOfferQueue($lockedDelivery);

                return Delivery::withoutGlobalScopes()->findOrFail($lockedDelivery->id);
            }

            $offer->update(['status' => 'declined', 'responded_at' => now()]);
            Rider::withoutGlobalScopes()->whereKey($rider->id)->update(['dispatch_turn_at' => now()]);
            $this->advanceOfferQueue($lockedDelivery);

            return Delivery::withoutGlobalScopes()->findOrFail($lockedDelivery->id);
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
        $completedDelivery = DB::transaction(function () use ($delivery, $proofPhoto, $tip) {
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
                'dispatch_turn_at' => now(),
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

        $this->assignPendingDeliveries();

        return $completedDelivery;
    }

    // ---- helpers ----

    private function syncOffersForDelivery(Delivery $delivery): int
    {
        if ($delivery->status !== 'pending_assignment') {
            return 0;
        }

        $delivery->loadMissing('order.store');
        $order = $delivery->order;
        $store = $order?->store;

        if (
            ! $store
            || ! $this->hasValidCoordinates($store->latitude, $store->longitude)
            || ! $this->hasValidCoordinates($order->customer_lat, $order->customer_lng)
        ) {
            return 0;
        }

        $riders = Rider::withoutGlobalScopes()
            ->where('status', 'online')
            ->where('is_verified', true)
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereDoesntHave('deliveries', fn (Builder $query) => $query
                ->withoutGlobalScopes()
                ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES))
            ->whereNotIn('id', CustomDelivery::query()
                ->whereNotNull('rider_id')
                ->whereIn('status', self::ACTIVE_CUSTOM_DELIVERY_STATUSES)
                ->select('rider_id'))
            ->get()
            ->filter(fn (Rider $rider): bool => $this->haversineDistance(
                (float) $store->latitude,
                (float) $store->longitude,
                (float) $rider->current_latitude,
                (float) $rider->current_longitude,
            ) <= self::SEARCH_RADIUS_KM)
            ->sortBy(fn (Rider $rider): array => [
                $rider->dispatch_turn_at?->getTimestamp() ?? 0,
                $rider->id,
            ])
            ->values();

        $existingOffers = RiderDeliveryOffer::query()
            ->where('delivery_id', $delivery->id)
            ->get()
            ->keyBy('rider_id');
        $nextPosition = (int) RiderDeliveryOffer::query()
            ->where('delivery_id', $delivery->id)
            ->max('queue_position');
        $syncedCount = 0;

        foreach ($riders as $rider) {
            $offer = $existingOffers->get($rider->id);

            if ($offer && in_array($offer->status, ['waiting', 'offered', 'accepted', 'declined', 'expired'], true)) {
                continue;
            }

            $nextPosition++;
            if ($offer) {
                $offer->update([
                    'queue_position' => $nextPosition,
                    'status' => 'waiting',
                    'offered_at' => null,
                    'expires_at' => null,
                    'responded_at' => null,
                ]);
            } else {
                RiderDeliveryOffer::create([
                    'delivery_id' => $delivery->id,
                    'rider_id' => $rider->id,
                    'queue_position' => $nextPosition,
                    'status' => 'waiting',
                ]);
            }
            $syncedCount++;
        }

        $this->advanceOfferQueue($delivery);

        return $syncedCount;
    }

    private function advanceOfferQueue(Delivery $delivery): void
    {
        $activeOffer = RiderDeliveryOffer::query()
            ->where('delivery_id', $delivery->id)
            ->where('status', 'offered')
            ->lockForUpdate()
            ->first();

        if ($activeOffer) {
            $rider = Rider::withoutGlobalScopes()->lockForUpdate()->find($activeOffer->rider_id);
            $turnIsCurrent = $activeOffer->expires_at?->isFuture() === true
                && $rider !== null
                && $this->isRiderAvailableForDelivery($rider, $delivery);

            if ($turnIsCurrent) {
                return;
            }

            $activeOffer->update([
                'status' => $activeOffer->expires_at?->isPast() ? 'expired' : 'unavailable',
                'responded_at' => now(),
            ]);
        }

        $waitingOffer = RiderDeliveryOffer::query()
            ->where('delivery_id', $delivery->id)
            ->where('status', 'waiting')
            ->orderBy('queue_position')
            ->lockForUpdate()
            ->first();

        while ($waitingOffer) {
            $rider = Rider::withoutGlobalScopes()->lockForUpdate()->find($waitingOffer->rider_id);

            if ($rider && $this->isRiderAvailableForDelivery($rider, $delivery)) {
                $waitingOffer->update([
                    'status' => 'offered',
                    'offered_at' => now(),
                    'expires_at' => now()->addSeconds(self::OFFER_WINDOW_SECONDS),
                ]);
                $rider->update(['dispatch_turn_at' => now()]);

                return;
            }

            $waitingOffer->update(['status' => 'unavailable', 'responded_at' => now()]);
            $waitingOffer = RiderDeliveryOffer::query()
                ->where('delivery_id', $delivery->id)
                ->where('status', 'waiting')
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->first();
        }

        $eligibleRiders = $this->eligibleRidersForDelivery($delivery);
        if ($eligibleRiders->isEmpty()) {
            return;
        }

        $position = 0;
        foreach ($eligibleRiders as $rider) {
            $offer = RiderDeliveryOffer::query()
                ->where('delivery_id', $delivery->id)
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['declined', 'expired', 'unavailable'])
                ->lockForUpdate()
                ->first();

            if (! $offer) {
                continue;
            }

            $position++;
            $offer->update([
                'queue_position' => $position,
                'status' => 'waiting',
                'offered_at' => null,
                'expires_at' => null,
                'responded_at' => null,
            ]);
        }

        $waitingOffer = RiderDeliveryOffer::query()
            ->where('delivery_id', $delivery->id)
            ->where('status', 'waiting')
            ->orderBy('queue_position')
            ->lockForUpdate()
            ->first();

        if (! $waitingOffer) {
            return;
        }

        $rider = Rider::withoutGlobalScopes()->lockForUpdate()->find($waitingOffer->rider_id);
        if ($rider && $this->isRiderAvailableForDelivery($rider, $delivery)) {
            $waitingOffer->update([
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addSeconds(self::OFFER_WINDOW_SECONDS),
            ]);
            $rider->update(['dispatch_turn_at' => now()]);
        }
    }

    private function eligibleRidersForDelivery(Delivery $delivery): Collection
    {
        $delivery->loadMissing('order.store');
        $store = $delivery->order?->store;

        if (! $store || ! $this->hasValidCoordinates($store->latitude, $store->longitude)) {
            return collect();
        }

        return Rider::withoutGlobalScopes()
            ->where('status', 'online')
            ->where('is_verified', true)
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereDoesntHave('deliveries', fn (Builder $query) => $query
                ->withoutGlobalScopes()
                ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES))
            ->whereNotIn('id', CustomDelivery::query()
                ->whereNotNull('rider_id')
                ->whereIn('status', self::ACTIVE_CUSTOM_DELIVERY_STATUSES)
                ->select('rider_id'))
            ->get()
            ->filter(fn (Rider $rider): bool => $this->haversineDistance(
                (float) $store->latitude,
                (float) $store->longitude,
                (float) $rider->current_latitude,
                (float) $rider->current_longitude,
            ) <= self::SEARCH_RADIUS_KM)
            ->sortBy(fn (Rider $rider): array => [
                $rider->dispatch_turn_at?->getTimestamp() ?? 0,
                $rider->id,
            ])
            ->values();
    }

    private function isRiderAvailableForDelivery(Rider $rider, Delivery $delivery): bool
    {
        if (
            $rider->status !== 'online'
            || ! $rider->is_verified
            || $rider->user?->status !== 'active'
            || ! $this->hasValidCoordinates($rider->current_latitude, $rider->current_longitude)
        ) {
            return false;
        }

        $delivery->loadMissing('order.store');
        $store = $delivery->order?->store;

        if (! $store || ! $this->hasValidCoordinates($store->latitude, $store->longitude)) {
            return false;
        }

        $distance = $this->haversineDistance(
            (float) $store->latitude,
            (float) $store->longitude,
            (float) $rider->current_latitude,
            (float) $rider->current_longitude,
        );

        return $distance <= self::SEARCH_RADIUS_KM
            && ! Delivery::withoutGlobalScopes()
                ->where('rider_id', $rider->id)
                ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES)
                ->exists()
            && ! CustomDelivery::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', self::ACTIVE_CUSTOM_DELIVERY_STATUSES)
                ->exists();
    }

    private function assertRiderCanAccept(Rider $rider, Delivery $delivery): void
    {
        if (! $this->isRiderAvailableForDelivery($rider, $delivery)) {
            throw new DeliveryException('You must be online and free of active deliveries to accept this order.');
        }
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
