<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $validated): void {
            $lockedOrder = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancel_reason' => $validated['reason'],
                'cancelled_at' => now(),
            ]);
        });

        return back()->with('status', "Order {$order->order_number} was cancelled.");
    }
}
