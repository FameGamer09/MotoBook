<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderTrackingController extends Controller
{
    public function updateLocation(Request $request, Order $order): JsonResponse
    {
        if (!$order->hasRider()) {
            abort(409, 'Order has not been assigned to a rider yet.');
        }

        $validated = $request->validate([
            'rider_lat' => ['required', 'numeric', 'between:-90,90'],
            'rider_lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy'  => ['nullable', 'numeric', 'min:0'],
            'heading'   => ['nullable', 'numeric', 'between:0,360'],
            'speed'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $authorized =
            (auth()->check() && auth()->id() === $order->rider_id) ||
            (auth()->check() && auth()->user()->isAdmin());

        if (!$authorized) {
            abort(403, 'You are not authorized to update location for this order.');
        }

        $order->update([
            'rider_lat' => $validated['rider_lat'],
            'rider_lng' => $validated['rider_lng'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
            'data' => [
                'order_id'   => $order->id,
                'rider_lat'  => (float) $order->rider_lat,
                'rider_lng'  => (float) $order->rider_lng,
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ], 200);
    }

    public function showTracking(Request $request, Order $order): JsonResponse
    {
        $user = auth()->user();

        $authorized = $user && (
            $user->isAdmin() ||
            $user->id === $order->customer_id ||
            $user->id === $order->rider_id
        );

        if (!$authorized) {
            abort(403, 'You are not authorized to view tracking for this order.');
        }

        $order->load([
            'customer:id,name,email',
            'rider:id,name,email',
        ]);

        $riderDistanceToCustomer = null;
        if ($order->hasRiderLocation()) {
            $riderDistanceToCustomer = $this->haversineDistance(
                (float) $order->rider_lat,
                (float) $order->rider_lng,
                (float) $order->customer_lat,
                (float) $order->customer_lng
            );
        }

        $riderDistanceToMerchant = null;
        if ($order->hasRiderLocation() && $order->hasMerchantLocation()) {
            $riderDistanceToMerchant = $this->haversineDistance(
                (float) $order->rider_lat,
                (float) $order->rider_lng,
                (float) $order->merchant_lat,
                (float) $order->merchant_lng
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id'                  => $order->id,
                    'order_number'        => $order->order_number,
                    'status'              => $order->status,
                    'total_amount'        => (float) $order->total_amount,
                    'delivery_fee'        => (float) $order->delivery_fee,
                    'payment_method'      => $order->payment_method,
                    'payment_status'      => $order->payment_status,
                    'payment_reference'   => $order->payment_reference,
                    'delivery_notes'      => $order->delivery_notes,
                    'pickup_address'      => $order->pickup_address,
                    'dropoff_address'     => $order->dropoff_address,
                    'accepted_at'         => $order->accepted_at?->toIso8601String(),
                    'picked_up_at'        => $order->picked_up_at?->toIso8601String(),
                    'delivered_at'        => $order->delivered_at?->toIso8601String(),
                    'created_at'          => $order->created_at->toIso8601String(),
                    'updated_at'          => $order->updated_at->toIso8601String(),
                ],
                'customer' => $order->customer ? [
                    'id'   => $order->customer->id,
                    'name' => $order->customer->name,
                ] : null,
                'rider' => $order->rider ? [
                    'id'   => $order->rider->id,
                    'name' => $order->rider->name,
                ] : null,
                'locations' => [
                    'merchant' => $order->hasMerchantLocation() ? [
                        'lat' => (float) $order->merchant_lat,
                        'lng' => (float) $order->merchant_lng,
                    ] : null,
                    'customer' => [
                        'lat' => (float) $order->customer_lat,
                        'lng' => (float) $order->customer_lng,
                    ],
                    'rider' => $order->hasRiderLocation() ? [
                        'lat' => (float) $order->rider_lat,
                        'lng' => (float) $order->rider_lng,
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
        $latTo   = deg2rad($lat2);
        $lngTo   = deg2rad($lng2);

        $deltaLat = $latTo - $latFrom;
        $deltaLng = $lngTo - $lngFrom;

        $a = sin($deltaLat / 2) ** 2 +
             cos($latFrom) * cos($latTo) *
             sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusMeters * $c, 2);
    }
}
