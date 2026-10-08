<?php

namespace App\Http\Controllers\Merchant;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,accepted,preparing,ready_for_pickup,assigned,out_for_delivery,delivered,completed,rejected,cancelled'],
        ]);

        $orders = Order::with('customer', 'items')
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('merchant.orders.index', [
            'orders' => $orders,
            'activeStatus' => $validated['status'] ?? null,
        ]);
    }

    public function show(Order $order)
    {
        return view('merchant.orders.show', [
            'order' => $order->load('customer', 'items', 'delivery.rider.user', 'payments'),
        ]);
    }

    public function accept(Order $order, OrderService $orderService)
    {
        return $this->transition($order, fn () => $orderService->acceptOrder($order));
    }

    public function reject(Request $request, Order $order, OrderService $orderService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->transition($order, fn () => $orderService->rejectOrder($order, $validated['reason']));
    }

    public function preparing(Order $order, OrderService $orderService)
    {
        return $this->transition($order, fn () => $orderService->markPreparing($order));
    }

    public function ready(Order $order, OrderService $orderService)
    {
        return $this->transition($order, fn () => $orderService->markReadyForPickup($order));
    }

    private function transition(Order $order, callable $action)
    {
        try {
            $action();
        } catch (OrderException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()->route('merchant.orders.show', $order)->with('status', 'Order status updated.');
    }
}
