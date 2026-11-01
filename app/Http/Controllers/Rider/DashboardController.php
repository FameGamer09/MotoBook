<?php

namespace App\Http\Controllers\Rider;

use App\Exceptions\DeliveryException;
use App\Http\Controllers\Controller;
use App\Models\CustomDelivery;
use App\Models\Delivery;
use App\Models\RiderDeliveryOffer;
use App\Models\RiderLocationLog;
use App\Models\Store;
use App\Services\RiderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, RiderAssignmentService $service): View
    {
        $rider = $request->user()->riderProfile;
        $activeStatuses = ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'en_route_to_customer', 'arrived_at_customer'];
        $deliveries = $rider
            ? $rider->deliveries()
                ->with('order.store', 'order.customer', 'order.items')
                ->whereIn('status', $activeStatuses)
                ->latest()
                ->get()
            : collect();
        $customDeliveries = $rider
            ? CustomDelivery::query()
                ->where(function ($query) use ($rider) {
                    $query->where('rider_id', $rider->id);
                    if ($rider->status === 'online') {
                        $query->orWhere('status', 'pending_assignment');
                    }
                })
                ->whereIn('status', ['pending_assignment', 'assigned', 'accepted', 'picked_up'])
                ->latest()
                ->get()
            : collect();
        $activeCustomDelivery = $customDeliveries->first(fn (CustomDelivery $delivery): bool => $delivery->rider_id === $rider?->id && in_array($delivery->status, ['accepted', 'picked_up'], true)
        );
        $hasActiveDelivery = $deliveries->isNotEmpty() || $customDeliveries->contains(fn (CustomDelivery $delivery): bool => $delivery->rider_id === $rider?->id && in_array($delivery->status, ['assigned', 'accepted', 'picked_up'], true)
        );
        $canGoOnline = $request->user()->status === 'active' && $rider?->is_verified && ! $hasActiveDelivery;
        $offers = $rider ? $service->riderOffers($rider) : collect();
        $earnings = $request->user()->wallet?->transactions()
            ->where('type', 'credit')
            ->whereDate('created_at', today())
            ->sum('amount') ?? 0;

        return view('rider.dashboard', [
            'rider' => $rider,
            'earnings' => $earnings,
            'deliveries' => $deliveries,
            'customDeliveries' => $customDeliveries,
            'activeCustomDelivery' => $activeCustomDelivery,
            'hasActiveDelivery' => $hasActiveDelivery,
            'canGoOnline' => $canGoOnline,
            'offers' => $offers,
            'stores' => Store::withoutGlobalScopes()->where('is_verified', true)->orderByDesc('is_open')->orderBy('name')->get(),
        ]);
    }

    public function offers(Request $request, RiderAssignmentService $service): JsonResponse
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        return response()->json([
            'offers' => $service->riderOffers($rider)->map(function (RiderDeliveryOffer $offer): array {
                $currentOffer = $offer->delivery->offers->firstWhere('status', 'offered');
                $remainingTurnSeconds = $currentOffer?->expires_at
                    ? max(0, now()->diffInSeconds($currentOffer->expires_at, false))
                    : 0;
                $isMyTurn = $offer->status === 'offered' && $offer->expires_at?->isFuture();

                return [
                    'id' => $offer->id,
                    'delivery_id' => $offer->delivery_id,
                    'status' => $offer->status,
                    'queue_position' => $offer->queue_position,
                    'can_accept' => $isMyTurn,
                    'wait_seconds' => $isMyTurn ? 0 : $remainingTurnSeconds + max(0, $offer->queue_position - ($currentOffer?->queue_position ?? 0) - 1) * 20,
                    'store' => $offer->delivery->order->store->name,
                    'order_number' => $offer->delivery->order->order_number,
                    'item_count' => $offer->delivery->order->items->sum('quantity'),
                    'total_amount' => (float) $offer->delivery->order->total_amount,
                    'expires_at' => $offer->expires_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function accept(Request $request, Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        return $this->transition($delivery, fn () => $service->riderAccept($delivery, $rider));
    }

    public function reject(Request $request, Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        return $this->transition($delivery, fn () => $service->riderReject($delivery, $rider));
    }

    public function startPickup(Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        return $this->transition($delivery, fn () => $service->startPickup($delivery));
    }

    public function arrivePickup(Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        return $this->transition($delivery, fn () => $service->arrivePickup($delivery));
    }

    public function confirmPickup(Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        return $this->transition($delivery, fn () => $service->confirmPickup($delivery));
    }

    public function startCustomer(Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        try {
            $service->startCustomerDelivery($delivery);
        } catch (DeliveryException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()->route('rider.orders.navigation', $delivery->order)
            ->with('status', 'Navigation started.');
    }

    public function arriveCustomer(Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        return $this->transition($delivery, fn () => $service->arriveCustomer($delivery));
    }

    public function complete(Request $request, Delivery $delivery, RiderAssignmentService $service): RedirectResponse
    {
        $validated = $request->validate(['tip' => ['nullable', 'numeric', 'min:0', 'max:99999.99']]);

        return $this->transition($delivery, fn () => $service->completeDelivery($delivery, null, (float) ($validated['tip'] ?? 0)));
    }

    public function updateLocation(Request $request): JsonResponse
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

    public function toggleStatus(Request $request, RiderAssignmentService $service): RedirectResponse
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        if ($rider->status === 'online') {
            $rider->update(['status' => 'offline']);

            return back()->with('status', 'You are now offline.');
        }

        abort_unless($request->user()->status === 'active' && $rider->is_verified, 403, 'Your account and rider profile must be approved before going online.');
        $hasActiveDelivery = $rider->deliveries()->whereIn('status', [
            'assigned',
            'accepted',
            'en_route_to_pickup',
            'arrived_at_pickup',
            'picked_up',
            'en_route_to_customer',
            'arrived_at_customer',
        ])->exists();
        $hasActiveCustomDelivery = CustomDelivery::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->exists();
        abort_if($hasActiveDelivery || $hasActiveCustomDelivery, 409, 'Complete or reject your active delivery before going online.');

        $rider->update(['status' => 'online']);
        $service->assignPendingDeliveries();

        return back()->with('status', 'You are now online. Pending deliveries have been checked for assignment.');
    }

    private function transition(Delivery $delivery, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DeliveryException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return back()->with('status', 'Delivery status updated.');
    }
}
