<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        $days = (int) $request->input('days', 7);
        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $from = now()->subDays($days - 1)->startOfDay();

        $orders = Order::with('items')
            ->where('created_at', '>=', $from)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->get();

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($orders) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Order', 'Date', 'Status', 'Payment', 'Total']);
                foreach ($orders as $order) {
                    fputcsv($handle, [$order->order_number, $order->created_at->toDateTimeString(), $order->status, $order->payment_method, $order->total_amount]);
                }
                fclose($handle);
            }, 'sales-report.csv', ['Content-Type' => 'text/csv']);
        }

        $dailySales = collect(range($days - 1, 0))->map(function (int $offset) use ($orders, $days) {
            $date = now()->subDays($offset);
            $dayOrders = $orders->filter(fn (Order $order) => $order->created_at->isSameDay($date));

            return [
                'label' => $date->format($days > 14 ? 'M j' : 'D'),
                'sales' => (float) $dayOrders->sum('total_amount'),
                'orders' => $dayOrders->count(),
            ];
        });

        $topItems = $orders->flatMap->items
            ->groupBy('item_name')
            ->map(fn ($items, $name) => [
                'name' => $name,
                'quantity' => $items->sum('quantity'),
                'revenue' => (float) $items->sum('subtotal'),
            ])
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        return view('merchant.sales.index', [
            'store' => $store,
            'days' => $days,
            'dailySales' => $dailySales,
            'topItems' => $topItems,
            'totalSales' => (float) $orders->sum('total_amount'),
            'orderCount' => $orders->count(),
            'averageOrder' => $orders->count() ? (float) $orders->avg('total_amount') : 0,
        ]);
    }
}
