<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomDelivery;
use App\Models\CustomerNotification;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomDeliveryController extends Controller
{
    public function create(Request $request)
    {
        return view('customer.custom-delivery.create', [
            'address' => $request->user()->addresses()->where('is_default', true)->first()
                ?? $request->user()->addresses()->first(),
            'deliveryFee' => 250,
            'serviceFee' => 15,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_landmark' => ['nullable', 'string', 'max:255'],
            'sender_name' => ['required', 'string', 'max:120'],
            'sender_phone' => ['required', 'string', 'max:30'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:pickup_longitude'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:pickup_latitude'],
            'dropoff_address' => ['required', 'string', 'max:500'],
            'dropoff_landmark' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'dropoff_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:dropoff_longitude'],
            'dropoff_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:dropoff_latitude'],
            'package_category' => ['required', 'in:documents,small_parcel,other'],
            'description' => ['required', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cod,gcash'],
        ]);

        $customDelivery = DB::transaction(function () use ($data, $request) {
            $customDelivery = $request->user()->customDeliveries()->create([
                ...$data,
                'delivery_fee' => 250,
                'service_fee' => 15,
                'total_amount' => 265,
                'payment_status' => 'unpaid',
            ]);

            $rider = $this->findAvailableRider($customDelivery);
            if ($rider) {
                $customDelivery->update([
                    'rider_id' => $rider->id,
                    'status' => 'assigned',
                ]);
                $rider->update(['status' => 'busy']);
            }

            if (($request->user()->customer_settings['order_notifications'] ?? true) !== false) {
                CustomerNotification::create([
                    'user_id' => $request->user()->id,
                    'type' => 'delivery',
                    'title' => 'Delivery request submitted',
                    'message' => $rider
                        ? 'Your delivery request has been sent to a nearby rider.'
                        : 'Your delivery request is waiting for an available rider.',
                    'action_url' => route('customer.custom-deliveries.show', $customDelivery, false),
                ]);
            }

            return $customDelivery;
        });

        return redirect()->route('customer.custom-deliveries.confirmation', $customDelivery);
    }

    public function confirmation(Request $request, CustomDelivery $customDelivery)
    {
        $this->authorizeCustomer($request, $customDelivery);

        return view('customer.custom-delivery.confirmation', compact('customDelivery'));
    }

    public function index(Request $request)
    {
        return view('customer.custom-delivery.index', [
            'deliveries' => $request->user()->customDeliveries()->with('rider.user')->latest()->get(),
        ]);
    }

    public function show(Request $request, CustomDelivery $customDelivery)
    {
        $this->authorizeCustomer($request, $customDelivery);

        return view('customer.custom-delivery.show', compact('customDelivery'));
    }

    public function tracking(Request $request, CustomDelivery $customDelivery)
    {
        $this->authorizeCustomer($request, $customDelivery);
        $customDelivery->load('rider.user');
        $rider = $customDelivery->rider;

        return response()->json([
            'status' => $customDelivery->status,
            'rider' => $rider ? [
                'name' => $rider->user?->name,
                'phone' => $rider->user?->phone,
                'latitude' => $rider->current_latitude,
                'longitude' => $rider->current_longitude,
                'updated_at' => $rider->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function cancel(Request $request, CustomDelivery $customDelivery)
    {
        $this->authorizeCustomer($request, $customDelivery);
        abort_unless(in_array($customDelivery->status, ['pending_assignment', 'assigned'], true), 409);

        DB::transaction(function () use ($customDelivery) {
            if ($customDelivery->rider) {
                $customDelivery->rider->update(['status' => 'online']);
            }
            $customDelivery->update(['status' => 'cancelled']);
        });

        return redirect()->route('customer.custom-deliveries.show', $customDelivery)
            ->with('status', 'Delivery request cancelled.');
    }

    private function authorizeCustomer(Request $request, CustomDelivery $customDelivery): void
    {
        abort_unless($customDelivery->user_id === $request->user()->id, 404);
    }

    private function findAvailableRider(CustomDelivery $delivery): ?Rider
    {
        $riders = Rider::query()
            ->where('status', 'online')
            ->where('is_verified', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->lockForUpdate()
            ->get();

        if ($riders->isEmpty()) {
            return null;
        }

        if ($delivery->pickup_latitude === null || $delivery->pickup_longitude === null) {
            return $riders->first();
        }

        return $riders->sortBy(fn (Rider $rider) => $this->distanceKm(
            $delivery->pickup_latitude,
            $delivery->pickup_longitude,
            $rider->current_latitude,
            $rider->current_longitude
        ))->first();
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
