<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function edit(Request $request)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        return view('merchant.store.edit', ['store' => $store]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'brand_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_open' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);

        foreach (['logo', 'banner'] as $image) {
            if ($request->hasFile($image)) {
                $validated[$image] = $request->file($image)->store('store-branding', 'public');
            }
        }

        if ($request->has('is_open')) {
            $validated['is_open'] = $request->boolean('is_open');
        } else {
            unset($validated['is_open']);
        }

        $store->update($validated);

        return back()->with('status', 'Store details updated successfully.');
    }

    public function toggle(Request $request)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        $store->update(['is_open' => ! $store->is_open]);

        return back()->with('status', $store->is_open ? 'Your store is now open.' : 'Your store is now closed.');
    }
}
