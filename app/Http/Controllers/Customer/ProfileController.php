<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['wallet', 'addresses']);

        $ordersCount = Order::withoutGlobalScopes()->where('customer_id', $user->id)->count();
        $reviewsCount = Review::where('customer_id', $user->id)->count();
        $customDeliveriesCount = $user->customDeliveries()->count();

        return view('customer.profile', [
            'user' => $user,
            'ordersCount' => $ordersCount,
            'reviewsCount' => $reviewsCount,
            'customDeliveriesCount' => $customDeliveriesCount,
            'loyaltyPoints' => Order::withoutGlobalScopes()->where('customer_id', $user->id)->whereIn('status', ['delivered', 'completed'])->count() * 10,
        ]);
    }

    public function addresses(Request $request)
    {
        return view('customer.addresses', [
            'addresses' => $request->user()->addresses,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return redirect()->route('customer.profile')->with('status', 'Profile updated.');
    }

    public function edit(Request $request)
    {
        return view('customer.profile-edit', ['user' => $request->user()]);
    }

    public function storeAddress(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'address_line' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $makeDefault = $request->user()->addresses()->count() === 0; // first address is auto-default

        $request->user()->addresses()->create([
            ...$validated,
            'is_default' => $makeDefault,
        ]);

        return back()->with('status', 'Address added.');
    }

    public function updateAddressLocation(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $address->update($validated);

        return back()->with('status', 'Delivery location updated.');
    }

    public function setDefaultAddress(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('status', 'Default address updated.');
    }

    public function destroyAddress(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $wasDefault = $address->is_default;
        $address->delete();

        // if we just deleted the default one, promote another address automatically
        if ($wasDefault) {
            $request->user()->addresses()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'Address removed.');
    }
}
