<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('merchant.settings.edit', [
            'store' => $request->user()->store,
            'settings' => $request->user()->store?->settings ?? [],
        ]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;
        $store->update([
            'settings' => [
                'order_notifications' => $request->boolean('order_notifications'),
                'low_stock_alerts' => $request->boolean('low_stock_alerts'),
                'weekly_sales_summary' => $request->boolean('weekly_sales_summary'),
            ],
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
