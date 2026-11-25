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

        return view('merchant.dashboard', [
            'store' => $store,
            'stats' => [
                'today_sales' => $store->orders()->whereDate('created_at', today())->whereIn('status', ['delivered', 'completed'])->sum('total_amount'),
                'menu_items' => $store->menuItems()->count(),
                'available_items' => $store->menuItems()->where('is_available', true)->count(),
                'unavailable_items' => $store->menuItems()->where('is_available', false)->count(),
            ],
            'menuItems' => $store->menuItems()->latest()->limit(5)->get(),
        ]);
    }
}
