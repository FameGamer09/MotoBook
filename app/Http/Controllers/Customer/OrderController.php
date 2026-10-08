<?php

namespace App\Http\Controllers\Customer;

use App\Http\Requests\Customer\SubmitOrderReviewRequest;
use App\Http\Controllers\Controller;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Review;
use App\Models\Rider;
use App\Models\RiderLocationLog;
use App\Models\Store;
use App\Services\OrderService;
use App\Services\ManagementOrderSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request, ManagementOrderSync $managementOrders)
    {
        $orders = Order::withoutGlobalScopes()
            ->where('customer_id', $request->user()->id)
            ->with('store', 'items', 'delivery')
            ->latest()
            ->get();

        $orders->whereIn('status', ['pending', 'accepted', 'preparing', 'ready_for_pickup', 'assigned', 'out_for_delivery'])
            ->each(function (Order $order) use ($managementOrders) {
                $managementOrders->pull($order);
                $order->refresh();
            });

        return view('customer.orders', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order, ManagementOrderSync $managementOrders)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        $managementTracking = $managementOrders->pull($order);
        $order->refresh();

        $order->load('store', 'items', 'delivery.rider.user', 'review');

        return view('customer.order-detail', [
            'order' => $order,
            'managementTracking' => $managementTracking,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $orderService)
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $orderService->cancelOrder($order, $validated['reason']);
        } catch (OrderException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('status', 'Your order has been cancelled.');
    }

    public function review(SubmitOrderReviewRequest $request, Order $order)
    {
        abort_unless(in_array($order->status, ['delivered', 'completed'], true), 409, 'You can review an order after delivery.');

        $validated = $request->validated();

        if (! $order->rider_id && isset($validated['rider_rating'])) {
            throw ValidationException::withMessages([
                'rider_rating' => 'This order does not have an assigned rider to review.',
            ]);
        }

        DB::transaction(function () use ($order, $request, $validated) {
            $lockedOrder = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($order->id);
            abort_unless($lockedOrder->customer_id === $request->user()->id, 404);

            if (! in_array($lockedOrder->status, ['delivered', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'store_rating' => 'You can review an order after delivery.',
                ]);
            }

            if (Review::where('order_id', $lockedOrder->id)->exists()) {
                throw ValidationException::withMessages([
                    'store_rating' => 'This order already has a review.',
                ]);
            }

            $lockedOrder->loadMissing('delivery');

            $review = Review::create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $request->user()->id,
                'store_id' => $lockedOrder->store_id,
                'rider_id' => $lockedOrder->rider_id,
                'store_rating' => $validated['store_rating'],
                'store_comment' => $validated['store_comment'] ?? null,
                'rider_rating' => $validated['rider_rating'] ?? null,
                'rider_comment' => $validated['rider_comment'] ?? null,
            ]);

            $store = Store::query()->lockForUpdate()->findOrFail($review->store_id);
            $store->update([
                'rating' => round((float) Review::where('store_id', $store->id)->avg('store_rating'), 1),
            ]);

            if ($review->rider_id && $review->rider_rating) {
                $rider = Rider::query()->lockForUpdate()->findOrFail($review->rider_id);
                $rider->update([
                    'rating' => round((float) Review::where('rider_id', $rider->id)->avg('rider_rating'), 1),
                ]);
            }
        });

        return redirect()->route('customer.orders.show', $order)
            ->with('status', 'Thank you for reviewing your order.');
    }

    public function tracking(Request $request, Order $order, ManagementOrderSync $managementOrders)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);
        $managementTracking = $managementOrders->pull($order);
        $order->refresh();
        $order->load('delivery.rider.user');
        $delivery = $order->delivery;
        $location = null;

        if ($delivery && $delivery->rider_id && in_array($order->status, ['assigned', 'out_for_delivery'], true)) {
            $latestLocation = RiderLocationLog::withoutGlobalScopes()
                ->where('rider_id', $delivery->rider_id)
                ->where('order_id', $order->id)
                ->latest('recorded_at')
                ->first();

            if ($latestLocation && $latestLocation->recorded_at->greaterThan(now()->subSeconds(90))) {
                $location = [
                    'latitude' => (float) $latestLocation->latitude,
                    'longitude' => (float) $latestLocation->longitude,
                    'recorded_at' => $latestLocation->recorded_at->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'status' => $order->status,
            'delivery_status' => $managementTracking['delivery_status'] ?? $delivery?->status,
            'rider' => $managementTracking['rider'] ?? ($delivery?->rider ? [
                'name' => $delivery->rider->user?->name,
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'updated_at' => $location['recorded_at'] ?? null,
            ] : null),
        ]);
    }
}
