<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        $rider = $request->user()->riderProfile;

        if (! $rider) {
            abort(404);
        }

        if ($request->user()->status === 'active' && $rider->is_verified && $rider->plate_number) {
            return redirect()->route('rider.dashboard');
        }

        return view('rider.onboarding', ['rider' => $rider]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_type' => ['required', 'in:motorcycle,bicycle,car'],
            'plate_number' => ['required', 'string', 'max:20'],
            'license_number' => ['required', 'string', 'max:50'],
            'license_photo' => ['required', 'image', 'max:2048'],
        ]);

        $licensePath = $request->file('license_photo')->store('rider-licenses', 'local');

        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        $rider->update([
            'vehicle_type' => $validated['vehicle_type'],
            'plate_number' => $validated['plate_number'],
            'license_number' => $validated['license_number'],
            'license_photo' => $licensePath,
            'status' => 'offline', // stays offline until admin verifies
            'is_verified' => false,
        ]);

        return redirect()->route('rider.onboarding')
            ->with('status', 'Details submitted. Your account must be activated and your rider profile verified before you can go online.');
    }
}
