<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RiderLocationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ManagementOrderSync;

class OrderTrackingController extends Controller
{
    public function updateLocation(Request $request, Order $order, ManagementOrderSync $managementOrders): JsonResponse
    {
        $rider = $order->rider;

        if (! $rider) {
            abort(409, 'Order has not been assigned to a rider yet.');
        }

        $validated = $request->validate([
            'rider_lat' => ['required', 'numeric', 'between:-90,90'],
            'rider_lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->user()?->id !== $rider->user_id && ! $request->user()?->isAdmin()) {
            abort(403, 'You are not authorized to update location for this order.');
        }

        DB::transaction(function () use ($order, $rider, $validated): void {
            $order->update([
                'rider_lat' => $validated['rider_lat'],
                'rider_lng' => $validated['rider_lng'],
            ]);

            $rider->update([
                'current_latitude' => $validated['rider_lat'],
                'current_longitude' => $validated['rider_lng'],
            ]);

            RiderLocationLog::create([
                'rider_id' => $rider->id,
                'order_id' => $order->id,
                'latitude' => $validated['rider_lat'],
                'longitude' => $validated['rider_lng'],
                'recorded_at' => now(),
            ]);
        });
        $managementOrders->pushLocation($order->fresh(), (float) $validated['rider_lat'], (float) $validated['rider_lng']);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
            'data' => [
                'order_id' => $order->id,
                'rider_lat' => (float) $order->rider_lat,
                'rider_lng' => (float) $order->rider_lng,
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ], 200);
    }

    public function showTracking(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $riderUserId = $order->rider?->user_id;

        $authorized = $user && (
            $user->isAdmin() ||
            $user->id === $order->customer_id ||
            $user->id === $riderUserId
        );

        if (! $authorized) {
            abort(403, 'You are not authorized to view tracking for this order.');
        }

        $order->load([
            'customer:id,name,email',
            'rider.user:id,name,email',
            'store:id,name,latitude,longitude',
            'deliveryAddress',
        ]);

        $riderLat = $order->rider_lat ?? $order->rider?->current_latitude;
        $riderLng = $order->rider_lng ?? $order->rider?->current_longitude;
        $customerLat = $order->customer_lat ?? $order->deliveryAddress?->latitude;
        $customerLng = $order->customer_lng ?? $order->deliveryAddress?->longitude;
        $merchantLat = $order->merchant_lat ?? $order->store?->latitude;
        $merchantLng = $order->merchant_lng ?? $order->store?->longitude;

        $riderDistanceToCustomer = null;
        if ($riderLat !== null && $riderLng !== null && $customerLat !== null && $customerLng !== null) {
            $riderDistanceToCustomer = $this->haversineDistance(
                (float) $riderLat,
                (float) $riderLng,
                (float) $customerLat,
                (float) $customerLng
            );
        }

        $riderDistanceToMerchant = null;
        if ($riderLat !== null && $riderLng !== null && $merchantLat !== null && $merchantLng !== null) {
            $riderDistanceToMerchant = $this->haversineDistance(
                (float) $riderLat,
                (float) $riderLng,
                (float) $merchantLat,
                (float) $merchantLng
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'delivery_fee' => (float) $order->delivery_fee,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'payment_reference' => $order->payment_reference,
                    'delivery_notes' => $order->delivery_notes ?? $order->customer_note,
                    'pickup_address' => $order->pickup_address ?? $order->store?->address,
                    'dropoff_address' => $order->dropoff_address ?? data_get($order->delivery_address_snapshot, 'address_line'),
                    'accepted_at' => $order->accepted_at?->toIso8601String(),
                    'picked_up_at' => $order->picked_up_at?->toIso8601String(),
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                    'created_at' => $order->created_at->toIso8601String(),
                    'updated_at' => $order->updated_at->toIso8601String(),
                ],
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                ] : null,
                'rider' => $order->rider ? [
                    'id' => $order->rider->user_id,
                    'name' => $order->rider->user?->name,
                ] : null,
                'locations' => [
                    'merchant' => $merchantLat !== null && $merchantLng !== null ? [
                        'lat' => (float) $merchantLat,
                        'lng' => (float) $merchantLng,
                    ] : null,
                    'customer' => $customerLat !== null && $customerLng !== null ? [
                        'lat' => (float) $customerLat,
                        'lng' => (float) $customerLng,
                    ] : null,
                    'rider' => $riderLat !== null && $riderLng !== null ? [
                        'lat' => (float) $riderLat,
                        'lng' => (float) $riderLng,
                        'updated_at' => $order->updated_at->toIso8601String(),
                    ] : null,
                ],
                'distances' => [
                    'rider_to_merchant_meters' => $riderDistanceToMerchant,
                    'rider_to_customer_meters' => $riderDistanceToCustomer,
                ],
            ],
        ], 200);
    }

    private function haversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadiusMeters = 6371000;

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $deltaLat = $latTo - $latFrom;
        $deltaLng = $lngTo - $lngFrom;

        $a = sin($deltaLat / 2) ** 2 +
             cos($latFrom) * cos($latTo) *
             sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusMeters * $c, 2);
    }
}
