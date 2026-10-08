<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        $store = $request->user()->store;

        if ($store && $request->user()->status === 'active') {
            return redirect()->route('merchant.dashboard');
        }

        return view('merchant.onboarding', ['store' => $store]);
    }

    public function store(Request $request)
    {
        if ($request->user()->store) {
            return redirect()->route(
                $request->user()->status === 'active' ? 'merchant.dashboard' : 'merchant.onboarding'
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('store-logos', 'public')
            : null;

        Store::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'category' => $validated['category'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'logo' => $logoPath,
            'is_open' => true,
            'is_verified' => false, // admin approves later
        ]);

        return redirect()->route('merchant.onboarding')
            ->with('status', 'Store submitted. An administrator must activate your account and verify the store before you can manage orders.');
    }
}
