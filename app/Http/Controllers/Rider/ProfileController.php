<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('rider.profile', [
            'user' => $request->user(),
            'rider' => $request->user()->riderProfile,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $rider = $user->riderProfile;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'vehicle_type' => ['required', 'in:motorcycle,bicycle,car'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'license_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->update(['name' => $validated['name'], 'phone' => $validated['phone'] ?? null]);
        $rider->update([
            'vehicle_type' => $validated['vehicle_type'],
            'plate_number' => $validated['plate_number'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'license_photo' => $request->hasFile('license_photo')
                ? $request->file('license_photo')->store('rider-licenses', 'local')
                : $rider->license_photo,
        ]);

        return back()->with('status', 'Profile updated.');
    }
}
