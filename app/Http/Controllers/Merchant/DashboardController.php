<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        $orders = $store->orders()->with('customer', 'items')->latest();

        return view('merchant.dashboard', [
            'store' => $store,
            'stats' => [
                'today_sales' => (clone $orders)->whereDate('created_at', today())->whereNotIn('status', ['rejected', 'cancelled'])->sum('total_amount'),
                'today_orders' => (clone $orders)->whereDate('created_at', today())->count(),
                'pending_orders' => (clone $orders)->where('status', 'pending')->count(),
                'menu_items' => $store->menuItems()->count(),
            ],
            'recentOrders' => (clone $orders)->limit(7)->get(),
            'menuItems' => $store->menuItems()->latest()->limit(5)->get(),
        ]);
    }
}
