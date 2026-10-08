<?php

namespace App\Http\Controllers\Rider;

use App\Exceptions\DeliveryException;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\CustomDelivery;
use App\Models\RiderLocationLog;
use App\Models\Store;
use App\Services\RiderAssignmentService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $rider = $request->user()->riderProfile;
        $customDeliveries = CustomDelivery::where(function ($query) use ($request, $rider) {
            $query->where('rider_id', $rider?->id);
            if ($rider?->status === 'online') {
                $query->orWhere('status', 'pending_assignment');
            }
        })->whereIn('status', ['pending_assignment', 'assigned', 'accepted', 'picked_up'])->latest()->get();

        return view('rider.dashboard', [
            'rider' => $rider,
            'earnings' => $request->user()->wallet?->balance ?? 0,
            'deliveries' => Delivery::with('order.store', 'order.customer', 'order.items')
                ->whereIn('status', ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'en_route_to_customer', 'arrived_at_customer'])
                ->latest()
                ->get(),
            'customDeliveries' => $customDeliveries,
            'activeCustomDelivery' => $customDeliveries->firstWhere('rider_id', $rider?->id),
            'stores' => Store::withoutGlobalScopes()->where('is_verified', true)->orderByDesc('is_open')->orderBy('name')->get(),
        ]);
    }

    public function accept(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->riderAccept($delivery));
    }

    public function reject(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->riderReject($delivery));
    }

    public function startPickup(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->startPickup($delivery));
    }

    public function arrivePickup(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->arrivePickup($delivery));
    }

    public function confirmPickup(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->confirmPickup($delivery));
    }

    public function startCustomer(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->startCustomerDelivery($delivery));
    }

    public function arriveCustomer(Delivery $delivery, RiderAssignmentService $service)
    {
        return $this->transition($delivery, fn () => $service->arriveCustomer($delivery));
    }

    public function complete(Request $request, Delivery $delivery, RiderAssignmentService $service)
    {
        $validated = $request->validate(['tip' => ['nullable', 'numeric', 'min:0', 'max:99999.99']]);

        return $this->transition($delivery, fn () => $service->completeDelivery($delivery, null, (float) ($validated['tip'] ?? 0)));
    }

    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_id' => ['required', 'integer'],
        ]);
        $rider = $request->user()->riderProfile;
        $delivery = $rider->deliveries()
            ->whereKey($validated['delivery_id'])
            ->whereIn('status', ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'en_route_to_customer', 'arrived_at_customer'])
            ->firstOrFail();

        $rider->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
        ]);
        RiderLocationLog::create([
            'rider_id' => $rider->id,
            'order_id' => $delivery->order_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => now(),
        ]);

        return response()->json([
            'latitude' => $rider->current_latitude,
            'longitude' => $rider->current_longitude,
            'recorded_at' => now()->toIso8601String(),
        ]);
    }

    public function toggleStatus(Request $request, RiderAssignmentService $service)
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        if ($rider->status === 'online') {
            $rider->update(['status' => 'offline']);

            return back()->with('status', 'You are now offline.');
        }

        abort_unless($request->user()->status === 'active' && $rider->is_verified, 403, 'Your account and rider profile must be approved before going online.');
        abort_if($rider->deliveries()->whereIn('status', [
            'assigned',
            'accepted',
            'en_route_to_pickup',
            'arrived_at_pickup',
            'picked_up',
            'en_route_to_customer',
            'arrived_at_customer',
        ])->exists(), 409, 'Complete or reject your active delivery before going online.');

        $rider->update(['status' => 'online']);
        $service->assignPendingDeliveries();

        return back()->with('status', 'You are now online. Pending deliveries have been checked for assignment.');
    }

    private function transition(Delivery $delivery, callable $action)
    {
        try {
            $action();
        } catch (DeliveryException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return back()->with('status', 'Delivery status updated.');
    }
}
