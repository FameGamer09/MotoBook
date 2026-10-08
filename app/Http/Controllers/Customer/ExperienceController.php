<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function toggleFavorite(Request $request, Store $store)
    {
        abort_unless($store->is_verified, 404);
        $request->user()->favoriteStores()->toggle($store->id);

        return back()->with('status', 'Your favorites have been updated.');
    }

    public function payments(Request $request)
    {
        return view('customer.payments', [
            'settings' => $request->user()->customer_settings ?? [],
        ]);
    }

    public function updatePayments(Request $request)
    {
        $data = $request->validate([
            'preferred_payment' => ['required', 'in:cod,wallet,gcash'],
            'gcash_name' => ['nullable', 'string', 'max:120'],
            'gcash_number' => ['nullable', 'string', 'max:30', 'required_if:preferred_payment,gcash'],
        ]);
        $settings = $request->user()->customer_settings ?? [];
        $settings['preferred_payment'] = $data['preferred_payment'];
        $settings['gcash_name'] = $data['gcash_name'] ?? null;
        $settings['gcash_number'] = $data['gcash_number'] ?? null;
        $request->user()->update(['customer_settings' => $settings]);

        return back()->with('status', 'Payment preferences saved. GCash orders remain pending until payment is confirmed.');
    }

    public function settings(Request $request)
    {
        return view('customer.settings', ['settings' => $request->user()->customer_settings ?? []]);
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->user()->customer_settings ?? [];
        $settings['dark_mode'] = $request->boolean('dark_mode');
        $settings['order_notifications'] = $request->boolean('order_notifications');
        $request->user()->update(['customer_settings' => $settings]);

        return back()->with('status', 'Settings saved.');
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()->customerNotifications()->latest()->paginate(20);
        $request->user()->customerNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return view('customer.notifications', compact('notifications'));
    }

    public function clearNotifications(Request $request)
    {
        $request->user()->customerNotifications()->delete();

        return back()->with('status', 'Notifications cleared.');
    }
}
