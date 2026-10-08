<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CustomDelivery;
use App\Models\CustomerNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomDeliveryController extends Controller
{
    public function location(Request $request, CustomDelivery $customDelivery)
    {
        $rider = $this->rider($request);
        abort_unless($customDelivery->rider_id === $rider->id && in_array($customDelivery->status, ['accepted', 'picked_up'], true), 404);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $rider->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
        ]);

        return response()->json(['latitude' => $rider->current_latitude, 'longitude' => $rider->current_longitude, 'updated_at' => now()->toIso8601String()]);
    }

    public function accept(Request $request, CustomDelivery $customDelivery)
    {
        $rider = $this->rider($request);
        DB::transaction(function () use ($customDelivery, $rider) {
            $job = CustomDelivery::lockForUpdate()->findOrFail($customDelivery->id);
            abort_unless($job->status === 'pending_assignment' || ($job->status === 'assigned' && $job->rider_id === $rider->id), 409);
            $alreadyAssigned = $job->rider_id === $rider->id && $job->status === 'assigned';
            abort_unless(($rider->status === 'online' || ($alreadyAssigned && $rider->status === 'busy')) && $rider->is_verified && $rider->user->status === 'active', 403);
            abort_if($rider->deliveries()->whereIn('status', ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'en_route_to_customer', 'arrived_at_customer'])->exists(), 409);
            $job->update(['rider_id' => $rider->id, 'status' => 'accepted', 'accepted_at' => now()]);
            $rider->update(['status' => 'busy']);
            $this->notifyCustomer($job, 'Rider accepted your delivery', 'Your rider is heading to the pickup location.');
        });

        return back()->with('status', 'Custom delivery accepted.');
    }

    public function reject(Request $request, CustomDelivery $customDelivery)
    {
        $rider = $this->rider($request);
        abort_unless($customDelivery->rider_id === $rider->id && $customDelivery->status === 'assigned', 404);
        DB::transaction(function () use ($customDelivery, $rider) {
            $customDelivery->update(['rider_id' => null, 'status' => 'pending_assignment']);
            $rider->update(['status' => 'online']);
        });

        return back()->with('status', 'Delivery offer declined. It is available for another rider.');
    }

    public function pickup(Request $request, CustomDelivery $customDelivery)
    {
        $rider = $this->rider($request);
        abort_unless($customDelivery->rider_id === $rider->id && $customDelivery->status === 'accepted', 409);
        $customDelivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $this->notifyCustomer($customDelivery, 'Package picked up', 'Your package is with the rider and on its way.');

        return back()->with('status', 'Package marked as picked up.');
    }

    public function complete(Request $request, CustomDelivery $customDelivery)
    {
        $rider = $this->rider($request);
        abort_unless($customDelivery->rider_id === $rider->id && $customDelivery->status === 'picked_up', 409);
        DB::transaction(function () use ($customDelivery, $rider) {
            $customDelivery->update(['status' => 'delivered', 'delivered_at' => now()]);
            $rider->update(['status' => 'online', 'total_deliveries' => $rider->total_deliveries + 1]);
            $rider->user->wallet()->firstOrCreate([], ['balance' => 0])->credit(
                $customDelivery->delivery_fee,
                "Custom delivery earning #{$customDelivery->id}",
                $customDelivery
            );
            $this->notifyCustomer($customDelivery, 'Package delivered', 'Your custom delivery has been completed.');
        });

        return back()->with('status', 'Custom delivery completed.');
    }

    private function rider(Request $request)
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        return $rider;
    }

    private function notifyCustomer(CustomDelivery $delivery, string $title, string $message): void
    {
        $customer = User::find($delivery->user_id);
        if (($customer?->customer_settings['order_notifications'] ?? true) === false) {
            return;
        }
        CustomerNotification::create([
            'user_id' => $delivery->user_id,
            'type' => 'delivery',
            'title' => $title,
            'message' => $message,
            'action_url' => route('customer.custom-deliveries.show', $delivery, false),
        ]);
    }
}
