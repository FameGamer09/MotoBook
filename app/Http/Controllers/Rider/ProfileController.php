<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CustomDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $rider = $user->riderProfile;
        $activeStatuses = ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup', 'picked_up', 'en_route_to_customer', 'arrived_at_customer'];
        $hasActiveDelivery = $rider && (
            $rider->deliveries()->whereIn('status', $activeStatuses)->exists()
            || CustomDelivery::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
                ->exists()
        );

        return view('rider.profile', [
            'user' => $user,
            'rider' => $rider,
            'hasActiveDelivery' => (bool) $hasActiveDelivery,
            'canGoOnline' => $user->status === 'active' && $rider?->is_verified && ! $hasActiveDelivery,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rider = $user->riderProfile;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'vehicle_type' => ['required', 'in:motorcycle,bicycle,car'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'license_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $verificationDetailsChanged = $validated['vehicle_type'] !== $rider->vehicle_type
            || ($validated['plate_number'] ?? null) !== $rider->plate_number
            || ($validated['license_number'] ?? null) !== $rider->license_number
            || $request->hasFile('license_photo');
        $licensePhoto = $request->hasFile('license_photo')
            ? $request->file('license_photo')->store('rider-licenses', 'local')
            : $rider->license_photo;

        $user->update(['name' => $validated['name'], 'phone' => $validated['phone'] ?? null]);
        $rider->update([
            'vehicle_type' => $validated['vehicle_type'],
            'plate_number' => $validated['plate_number'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'license_photo' => $licensePhoto,
            'is_verified' => $verificationDetailsChanged ? false : $rider->is_verified,
            'status' => $verificationDetailsChanged ? 'offline' : $rider->status,
        ]);

        return back()->with('status', $verificationDetailsChanged
            ? 'Profile updated. Your changed vehicle or license details must be verified again before you can go online.'
            : 'Profile updated.');
    }
}
